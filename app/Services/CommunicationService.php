<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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
            $attachTicket = in_array($emailType, ['registration_confirmation', 'payment_success']);

            Mail::send($template, $data, function ($message) use ($registration, $subject, $attachTicket) {
                $message->to($registration->email)
                    ->subject($subject);

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
            $token = config('services.sparrow.token');
            $from = config('services.sparrow.from');

            if (empty($token)) {
                logger()->info("SMS to {$registration->phone}: {$message}");
                $comm->markSent();

                return $comm;
            }

            $response = Http::asForm()->post('https://api.sparrowsms.com/v2/sms/', [
                'token' => $token,
                'from' => $from,
                'to' => $registration->phone,
                'text' => $message,
            ]);

            if ($response->successful()) {
                $comm->markSent($response->json('response_id'));
            } else {
                $comm->markFailed(['response' => $response->body(), 'status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
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

        if ($emailType === 'payment_success' || $emailType === 'payment_failed') {
            $data['payment'] = $registration->payment;
        }

        return $data;
    }
}
