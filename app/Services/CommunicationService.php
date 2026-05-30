<?php

namespace App\Services;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Facades\Http;
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

            if (empty($token)) {
                // In development without credentials, log instead
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
}
