<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\EmailDraft;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ThirdFactorService;

class CommunicationService
{
    /** Renders an email template with real data, without sending or logging anything — for local preview. */
    public function renderPreview(Registration $registration, Event $event, string $emailType, ?EmailDraft $draft = null): string
    {
        [$template, $data] = $this->buildView($registration, $event, $emailType, $draft);

        return view($template, $data)->render();
    }

    /**
     * View + data for one email. Content lives in the event's drafts; an event with no
     * draft for this type gets the neutral emails.default instead.
     *
     * @return array{0: string, 1: array}
     */
    private function buildView(Registration $registration, Event $event, string $emailType, ?EmailDraft $draft): array
    {
        $type = $draft?->type ?? $emailType;
        $needsFace = in_array($type, ['face_verification', 'invitation_face_verification'], true)
            || $draft?->include_face_link || $draft?->usesTag('face_url');

        $extra = [];
        if ($needsFace) {
            $extra['face_url'] = (string) $this->faceEnrollmentUrl($registration);
        }

        if (! $draft) {
            return ['emails.default', [
                'event' => $event,
                'registration' => $registration,
                'partnerLogo' => $event->partnerLogoUrls()[0] ?? null,
                'copy' => self::DEFAULT_COPY[$type] ?? self::DEFAULT_COPY['invitation'],
                'showQr' => in_array($type, ['invitation', 'invitation_face_verification', 'registration_confirmation', 'payment_success', 'event_reminder'], true),
                'faceEnrollmentUrl' => $extra['face_url'] ?? null,
            ]];
        }

        if ($draft->isHtml()) {
            if ($draft->usesTag('qr_svg')) {
                $extra['qr_svg'] = app(QRCodeService::class)->generateSvg($registration);
            }

            return ['emails.raw', ['html' => $draft->renderBody($registration, $event, $extra)]];
        }

        return ['emails.custom', [
            'event' => $event,
            'registration' => $registration,
            'partnerLogo' => $event->partnerLogoUrls()[0] ?? null,
            'body' => $draft->renderBody($registration, $event, $extra),
            'showQr' => $draft->include_qr,
            'faceEnrollmentUrl' => $draft->include_face_link ? ($extra['face_url'] ?? null) : null,
        ]];
    }

    /** Neutral wording for events that have not written their own draft of a type. */
    private const DEFAULT_COPY = [
        'invitation' => ['You are invited', 'You are invited to :event. Present the QR code below at the entrance.'],
        'invitation_face_verification' => ['You are invited', 'You are invited to :event. Present the QR code below at the entrance and complete the face verification.'],
        'face_verification' => ['Face verification', 'Please complete a quick face verification for :event.'],
        'registration_confirmation' => ['Registration confirmed', 'Your registration for :event is confirmed. Present the QR code below at the entrance.'],
        'payment_success' => ['Payment confirmed', 'Your payment for :event has been received. Present the QR code below at the entrance.'],
        'payment_failed' => ['Payment failed', 'We could not process your payment for :event. Please try again or contact the organisers.'],
        'event_reminder' => ['Event reminder', ':event is coming up. Keep your QR code handy for entry.'],
        'post_event_thank_you' => ['Thank you', 'Thank you for attending :event. We hope to see you again.'],
        'urgent_update' => ['Important update', 'There is an important update about :event. Please contact the organisers for details.'],
    ];

    private function faceEnrollmentUrl(Registration $registration): ?string
    {
        $thirdFactor = app(ThirdFactorService::class);

        if ($thirdFactor->enabled() && ! $registration->thirdfactor_verification_url) {
            $thirdFactor->createEnrollmentSession($registration);
            $registration->refresh();
        }

        return $registration->thirdfactor_verification_url;
    }

    private function mailerFor(Event $event): \Illuminate\Mail\Mailer
    {
        // Each event may bring its own Resend account (and so its own sending domain).
        // build() makes a fresh mailer per call, so a long-running queue worker never
        // holds on to a stale key.
        return $event->resend_api_key
            ? Mail::build(['transport' => 'resend-event', 'key' => $event->resend_api_key])
            : Mail::mailer();
    }

    /** @return array{0: string, 1: string} */
    private function senderFor(Event $event, ?EmailDraft $draft, string $emailType): array
    {
        $urgent = $emailType === 'urgent_update';
        $configured = (string) config('mail.from.address');

        // Without a draft address: same mailbox name as before, on the event's own domain
        // when it has one (the global address would be rejected under another account).
        $local = $urgent ? 'notice' : Str::before($configured, '@');
        $domain = $event->mail_domain ?: ($urgent ? 'digitalconclave.org' : Str::after($configured, '@'));

        return [
            $draft?->from_address ?: "{$local}@{$domain}",
            $draft?->from_name ?: (string) config('mail.from.name'),
        ];
    }

    /**
     * $draft: pass one to send exactly that draft (bulk sends); omit it and the event's
     * default draft for $emailType, if any, supplies sender and subject.
     */
    public function sendEmail(Registration $registration, Event $event, string $subject, string $emailType = 'invitation', ?EmailDraft $draft = null): Communication
    {
        // A logged "custom:12" type (the Resend button) points back at its draft.
        if (! $draft && str_starts_with($emailType, 'custom:')) {
            $draft = $event->emailDrafts()->find((int) Str::after($emailType, 'custom:'));
        }

        $explicitDraft = $draft !== null;
        $draft ??=$event->emailDrafts()->where('type', $emailType)->where('is_default', true)->first();

        // An explicit send keeps the subject the operator typed; automatic ones take the draft's.
        $subject = $draft?->renderSubject($registration, $event, $explicitDraft ? $subject : ($draft->subject ?: $subject)) ?? $subject;

        if ($draft?->isCustom()) {
            $emailType = $draft->communicationType();
        }

        // A retried/duplicated job (e.g. queue worker restarted mid-batch) must not
        // re-send to guests who already got this same email moments ago.
        $recent = Communication::where('registration_id', $registration->id)
            ->where('type', 'email')
            ->where('email_type', $emailType)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes(60))
            ->first();

        if ($recent) {
            return $recent;
        }

        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => $emailType,
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            [$template, $data] = $this->buildView($registration, $event, $emailType, $draft);
            // The invitation is the ticket: guests show it at the entrance.
            $attachTicket = $draft
                ? $draft->attach_ticket
                : in_array($emailType, EmailDraft::TICKET_TYPES, true);
            [$fromAddress, $fromName] = $this->senderFor($event, $draft, $draft?->type ?? $emailType);

            $this->mailerFor($event)->send($template, $data, function ($message) use ($registration, $subject, $attachTicket, $emailType, $event, $draft, $fromAddress, $fromName) {
                // Emails pasted from PDFs/docs often carry invisible unicode (zero-width
                // spaces, BOM) that RFC 2822 addr-spec validation flatly rejects.
                $sanitized = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $registration->email);
                // A guest may list more than one address, comma-separated — send to all of them.
                $emails = Registration::splitMultiValue($sanitized);

                // Always explicit: an on-demand mailer has no global "from" configured.
                $message->to($emails)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);

                // Urgent notices (e.g. postponement) go out under a distinct address (see
                // senderFor) so they read as an official notice, with the banner attached.
                if (($draft?->type ?? $emailType) === 'urgent_update') {
                    if ($event->banner_path && Storage::disk('public')->exists($event->banner_path)) {
                        $message->attachData(
                            Storage::disk('public')->get($event->banner_path),
                            'Notice.png',
                            ['mime' => 'image/png'],
                        );
                    }
                }

                if ($draft?->reply_to) {
                    $message->replyTo($draft->reply_to);
                } elseif ($replyTo = config('mail.reply_to.address')) {
                    $message->replyTo($replyTo, config('mail.reply_to.name'));
                }

                if ($attachTicket) {
                    try {
                        $ticketService = app(TicketService::class);
                        $pdf = $ticketService->generatePdf($registration);
                        $fileName = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $registration->displayName())) ?: 'Guest';
                        $message->attachData($pdf, "{$fileName}-Ticket.pdf", [
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

            $response = $this->sendSmsRequest($registration->phones(), $message);

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
            $regPhones = $registration?->phones() ?? [];
            if (! $registration || empty($regPhones)) {
                continue;
            }

            $comms[] = Communication::create([
                'registration_id' => $registration->id,
                'type' => 'sms',
                'email_type' => $emailType,
                'content' => $message,
                'status' => 'pending',
            ]);

            // A guest may list more than one number, comma-separated — send to all of them.
            array_push($phones, ...$regPhones);
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
                    if (array_intersect($reg->phones(), $invalidNumbers)) {
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
}
