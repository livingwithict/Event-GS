<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkEmail;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThirdFactorWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.thirdfactor.webhook_secret');
        $header = $request->header('X-Webhook-Signature', '');

        if (! $secret || ! $this->verify($secret, $header, $request->getContent())) {
            logger()->warning('ThirdFactor webhook signature rejected');

            return response('invalid signature', 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event_type'] ?? '';
        $sessionId = $payload['session_id'] ?? null;
        $status = $payload['status'] ?? '';

        $registration = $sessionId
            ? Registration::where('thirdfactor_session_id', $sessionId)->first()
            : null;

        if (! $registration) {
            logger()->info('ThirdFactor webhook: no matching registration', [
                'event' => $event,
                'session_id' => $sessionId,
                'raw_payload' => $payload,
            ]);

            return response('ok', 200);
        }

        $wasAlreadyApproved = $registration->thirdfactor_status === 'approved';

        $registration->update([
            'thirdfactor_status' => $status,
            'thirdfactor_enrolled_at' => $status === 'approved' ? now() : $registration->thirdfactor_enrolled_at,
        ]);

        // First time this registration clears verification: send the ticket —
        // unless "Invitation + Face Verification" already attached it up front.
        $ticketAlreadySent = $registration->communications()
            ->where('type', 'email')
            ->whereIn('email_type', ['invitation', 'invitation_face_verification'])
            ->where('status', 'sent')
            ->exists();

        if ($status === 'approved' && ! $wasAlreadyApproved && $registration->email && ! $ticketAlreadySent) {
            SendBulkEmail::dispatch([$registration->id], $registration->event_id, 'Your Event Ticket', 'invitation');
        }

        return response('ok', 200);
    }

    private function verify(string $secret, string $header, string $rawBody): bool
    {
        parse_str(str_replace(',', '&', $header), $parts);
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        return hash_equals($expected, $signature);
    }
}
