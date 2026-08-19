<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Services\ThirdFactorService;

class CommunicationService
{
    public function sendEmail(Registration $registration, Event $event, string $subject, string $emailType = 'invitation'): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => $emailType,
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            $template = $this->getTemplateForType($emailType);
            $data = $this->getTemplateData($registration, $event, $emailType);
            // The invitation is the ticket: guests show it at the entrance.
            $attachTicket = in_array($emailType, ['invitation', 'registration_confirmation', 'payment_success']);

            Mail::send($template, $data, function ($message) use ($registration, $subject, $attachTicket) {
                $message->to($registration->email)
                    ->subject($subject);

                if ($replyTo = config('mail.reply_to.address')) {
                    $message->replyTo($replyTo, config('mail.reply_to.name'));
                }

                if ($attachTicket) {
                    try {
                        $ticketService = app(TicketService::class);
                        $pdf = $ticketService->generatePdf($registration);
                        $message->attachData($pdf, $registration->guest_number.'-ticket.pdf', [
                            'mime' => 'application/pdf',
                        ]);
                    } catch (\Throwable $e) {
                        logger()->error('Failed to attach ticket to email: '.$e->getMessage());
                    }
                }
            });

            $comm->markSent();
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }

    public function sendSms(Registration $registration, string $message, ?string $emailType = null): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'sms',
            'email_type' => $emailType,
            'content' => $message,
            'status' => 'pending',
        ]);

        try {
            if (! $this->shouldSendSms()) {
                logger()->info("SMS to {$registration->phone}: {$message}");
                $comm->markSent();

                return $comm;
            }

            $response = $this->sendSmsRequest([$registration->phone], $message);

            if ($response['success']) {
                $comm->markSent((string) ($response['data']['ntc'] ?? ''));
            } elseif ($response['invalid_numbers']) {
                $comm->markFailed(['invalid_number' => true, 'response' => $response['data']]);
            } else {
                $comm->markFailed(['response' => $response['data'], 'status' => $response['status'] ?? null]);
            }
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }

    public function sendBatchSms(array $registrations, string $message, ?string $emailType = null): array
    {
        $comms = [];
        $phones = [];

        foreach ($registrations as $registration) {
            if (is_int($registration)) {
                $registration = Registration::find($registration);
            }
            if (! $registration || ! $registration->phone) {
                continue;
            }

            $comms[] = Communication::create([
                'registration_id' => $registration->id,
                'type' => 'sms',
                'email_type' => $emailType,
                'content' => $message,
                'status' => 'pending',
            ]);

            $phones[] = $registration->phone;
        }

        if (empty($phones)) {
            return $comms;
        }

        try {
            if (! $this->shouldSendSms()) {
                foreach ($comms as $comm) {
                    $reg = Registration::find($comm->registration_id);
                    logger()->info("SMS to {$reg->phone}: {$message}");
                    $comm->markSent();
                }

                return $comms;
            }

            $response = $this->sendSmsRequest($phones, $message);

            if ($response['success']) {
                foreach ($comms as $comm) {
                    $comm->markSent();
                }
            } elseif ($response['invalid_numbers']) {
                $invalidNumbers = $response['data']['invalid_number'] ?? [];
                foreach ($comms as $comm) {
                    $reg = Registration::find($comm->registration_id);
                    if (in_array($reg->phone, $invalidNumbers)) {
                        $comm->markFailed(['invalid_number' => true, 'response' => $response['data']]);
                    } else {
                        $comm->markSent();
                    }
                }
            } else {
                foreach ($comms as $comm) {
                    $comm->markFailed(['response' => $response['data'], 'status' => $response['status'] ?? null]);
                }
            }
        } catch (\Throwable $e) {
            foreach ($comms as $comm) {
                $comm->markFailed(['error' => $e->getMessage()]);
            }
        }

        return $comms;
    }

    public function checkBalance(): array
    {
        $token = config('services.sparrow.token');
        $baseUrl = config('services.sparrow.base_url');

        if (empty($token)) {
            return ['balance' => 'N/A', 'ntc_rate' => 'N/A', 'ncell_rate' => 'N/A', 'smartcell_rate' => 'N/A'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$baseUrl}/balance");

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json('message', 'Balance check failed')];
    }

    private function shouldSendSms(): bool
    {
        return in_array(config('services.sparrow.driver'), ['sociair', 'sparrow'], true)
            && ! empty(config('services.sparrow.token'));
    }

    private function sendSmsRequest(array $phoneNumbers, string $message): array
    {
        $token = config('services.sparrow.token');
        $baseUrl = config('services.sparrow.base_url');
        $mobile = implode(',', array_map([$this, 'normalizePhone'], $phoneNumbers));

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$baseUrl}/sms", [
                'message' => $message,
                'mobile' => $mobile,
            ]);

        $data = $response->json();
        $success = $response->successful()
            && isset($data['message'])
            && str_contains($data['message'], 'Success');

        $invalidNumbers = ! empty($data['invalid_number']);

        return [
            'success' => $success,
            'invalid_numbers' => $invalidNumbers,
            'data' => $data,
            'status' => $response->status(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', $phone);

        if (str_starts_with($digits, '977') && strlen($digits) === 13) {
            return substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return substr($digits, 1);
        }

        return $digits;
    }

    public function sendRegistrationConfirmation(Registration $registration, Event $event): void
    {
        if (! $event->settingEnabled('enable_notifications')) {
            return;
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Registration Confirmed: {$event->name}", 'registration_confirmation');
        }

        if ($registration->phone) {
            $this->sendSms(
                $registration,
                "Hello {$registration->name}, your registration for {$event->name} is confirmed. Guest #: {$registration->guest_number}",
                'registration_confirmation'
            );
        }
    }

    public function sendPaymentSuccess(Registration $registration, Event $event, Payment $payment): void
    {
        if (! $event->settingEnabled('enable_notifications')) {
            return;
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Payment Confirmed: {$event->name}", 'payment_success');
        }

        if ($registration->phone) {
            $amount = $payment->getAmountRupees();
            $this->sendSms(
                $registration,
                "Payment of NPR {$amount} confirmed for {$event->name}. Guest #: {$registration->guest_number}",
                'payment_success'
            );
        }
    }

    public function sendPaymentFailed(Registration $registration, Event $event): void
    {
        if (! $event->settingEnabled('enable_notifications')) {
            return;
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Payment Failed: {$event->name}", 'payment_failed');
        }

        if ($registration->phone) {
            $this->sendSms(
                $registration,
                "Your payment for {$event->name} could not be processed. Please try again.",
                'payment_failed'
            );
        }
    }

    public function sendEventReminder(Registration $registration, Event $event): void
    {
        if (! $event->settingEnabled('enable_notifications')) {
            return;
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Reminder: {$event->name} is tomorrow!", 'event_reminder');
        }

        if ($registration->phone) {
            $date = $event->start_datetime?->format('M j, Y') ?? $event->event_date?->format('M j, Y');
            $this->sendSms(
                $registration,
                "Reminder: {$event->name} is tomorrow ({$date}) at {$event->venue}. Guest #: {$registration->guest_number}",
                'event_reminder'
            );
        }
    }

    public function sendPostEventThankYou(Registration $registration, Event $event): void
    {
        if (! $event->settingEnabled('enable_notifications')) {
            return;
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Thank you for attending {$event->name}!", 'post_event_thank_you');
        }

        if ($registration->phone) {
            $this->sendSms(
                $registration,
                "Thank you for attending {$event->name}! We hope you had a great experience. - ICT Foundation Nepal",
                'post_event_thank_you'
            );
        }
    }

    public function sendUrgentUpdate(Registration $registration, Event $event, string $message): void
    {
        if ($registration->phone) {
            $this->sendSms(
                $registration,
                "URGENT - {$event->name}: {$message}",
                'urgent_update'
            );
        }

        if ($registration->email) {
            $this->sendEmail($registration, $event, "Urgent Update: {$event->name}", 'urgent_update');
        }
    }

    private function getTemplateForType(string $emailType): string
    {
        return match ($emailType) {
            'registration_confirmation' => 'emails.registration_confirmation',
            'payment_success' => 'emails.payment_success',
            'payment_failed' => 'emails.payment_failed',
            'event_reminder' => 'emails.event_reminder',
            'post_event_thank_you' => 'emails.post_event_thank_you',
            'urgent_update' => 'emails.urgent_update',
            'face_verification' => 'emails.face_verification',
            default => 'emails.invitation',
        };
    }

    private function getTemplateData(Registration $registration, Event $event, string $emailType): array
    {
        $data = [
            'event' => $event,
            'registration' => $registration,
        ];

        if (in_array($emailType, ['registration_confirmation', 'payment_success', 'event_reminder', 'invitation'])) {
            $qrService = app(QRCodeService::class);
            $data['qrCodeSvg'] = $qrService->generateSvg($registration);
        }

        if ($emailType === 'invitation') {
            try {
                $data['ticketJpeg'] = app(TicketService::class)->generateJpeg($registration);
            } catch (\Throwable $e) {
                logger()->error('Failed to rasterize ticket for inline embed: '.$e->getMessage());
            }
        }

        if ($emailType === 'face_verification') {
            $thirdFactor = app(ThirdFactorService::class);
            if ($thirdFactor->enabled() && ! $registration->thirdfactor_verification_url) {
                $thirdFactor->createEnrollmentSession($registration);
                $registration->refresh();
            }
            $data['faceEnrollmentUrl'] = $registration->thirdfactor_verification_url;
        }

        if ($emailType === 'payment_success' || $emailType === 'payment_failed') {
            $data['payment'] = $registration->payment;
        }

        return $data;
    }
}
