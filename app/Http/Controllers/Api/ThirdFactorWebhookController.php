<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $event = $payload['event'] ?? '';
        $sessionId = $payload['data']['session']['id'] ?? $payload['id'] ?? null;

        $registration = $sessionId
            ? Registration::where('thirdfactor_session_id', $sessionId)->first()
            : null;

        if (! $registration) {
            logger()->info('ThirdFactor webhook: no matching registration', ['event' => $event, 'session_id' => $sessionId]);

            return response('ok', 200);
        }

        $status = match (true) {
            str_contains($event, 'approved') => 'approved',
            str_contains($event, 'declined') => 'declined',
            str_contains($event, 'review') => 'review',
            str_contains($event, 'expired') => 'expired',
            str_contains($event, 'abandoned') => 'abandoned',
            default => $event,
        };

        $registration->update([
            'thirdfactor_status' => $status,
            'thirdfactor_enrolled_at' => $status === 'approved' ? now() : $registration->thirdfactor_enrolled_at,
        ]);

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
