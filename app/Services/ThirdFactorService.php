<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\Http;

class ThirdFactorService
{
    public function enabled(): bool
    {
        return filled(config('services.thirdfactor.api_key'));
    }

    /**
     * Mint a hosted face-enrollment link for a registrant via POST /v3/session/
     * and store the session id/url on the registration.
     */
    public function createEnrollmentSession(Registration $registration): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $baseUrl = rtrim(config('services.thirdfactor.base_url'), '/');

        $response = Http::withHeaders(['X-API-Key' => config('services.thirdfactor.api_key')])
            ->acceptJson()
            ->post("{$baseUrl}/v3/session/", array_filter([
                'workflow_id' => config('services.thirdfactor.workflow_id'),
                'vendor_data' => $registration->unique_code,
                'contact_email' => $registration->email,
                'contact_phone' => $registration->phone,
                'prefill' => array_filter([
                    'full_name' => $registration->name,
                    'email' => $registration->email,
                    'phone' => $registration->phone,
                ]),
            ]));

        if (! $response->successful()) {
            logger()->error('ThirdFactor session creation failed', [
                'registration_id' => $registration->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        $registration->update([
            'thirdfactor_session_id' => $data['id'] ?? null,
            'thirdfactor_verification_url' => $data['url'] ?? null,
            'thirdfactor_status' => $data['status'] ?? 'not_started',
        ]);

        return $data;
    }
}
