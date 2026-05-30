<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;

class CommunicationService
{
    public function sendEmail(Registration $registration, Event $event, string $subject): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            $qrService = app(QRCodeService::class);
            $qrCodeSvg = $qrService->generateSvg($registration);

            Mail::send('emails.invitation', [
                'event' => $event,
                'registration' => $registration,
                'qrCodeSvg' => $qrCodeSvg,
            ], function ($message) use ($registration, $subject) {
                $message->to($registration->email)
                    ->subject($subject);
            });

            $comm->markSent();
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }

    public function sendSms(Registration $registration, string $message): Communication
    {
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'sms',
            'status' => 'pending',
        ]);

        try {
            $token = config('services.sparrow.token');
            $from = config('services.sparrow.from');

            // In production, send via Sparrow SMS API
            // For now, log the attempt
            logger()->info("SMS to {$registration->phone}: {$message}");

            $comm->markSent();
        } catch (\Throwable $e) {
            $comm->markFailed(['error' => $e->getMessage()]);
        }

        return $comm;
    }
}
