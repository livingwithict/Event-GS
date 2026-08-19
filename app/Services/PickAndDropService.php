<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for PickAndDrop's courier API (https://pickndrop.apidog.io).
 * Auth is a single shared token, so callers never see the HTTP shape.
 */
class PickAndDropService
{
    private function client()
    {
        return Http::baseUrl(config('services.pickndrop.base_url'))
            ->withHeaders([
                'Authorization' => 'token '.config('services.pickndrop.api_key').':'.config('services.pickndrop.api_secret'),
                'Content-Type' => 'application/json',
            ])
            ->throw();
    }

    /**
     * Active branches, cached: this rarely changes and every "Get Branches"
     * call would otherwise round-trip on each dropdown render.
     *
     * @return array<int, array{name: string, branch_name: string}>
     */
    public function getBranches(): array
    {
        return Cache::remember('pickndrop.branches', now()->addHours(6), function () {
            $response = $this->client()->get('/api/method/logi360.api.get_branches');

            return $response->json('message.data.branches') ?? [];
        });
    }

    /**
     * Creates a courier order for one guest's physical delivery. COD is
     * always zero: we're shipping an invitation, not collecting payment.
     * Delivery status itself is tracked in PickAndDrop's own portal.
     */
    public function createOrder(Registration $registration): array
    {
        $response = $this->client()->post('/api/v2/method/logi360.api.create_order', [
            'customerName' => $registration->displayName(),
            'primaryMobileNo' => $registration->phone,
            'destinationBranch' => $registration->destination_branch,
            'codAmount' => 0,
            'orderDescription' => 'Event invitation',
            'landmark' => $registration->address,
            'ref' => (string) $registration->id,
        ]);

        return $response->json('message.data');
    }

    /** Tells PickAndDrop a batch is ready for their courier to collect. */
    public function createPickupRequest(string $vendorAddress): string
    {
        $response = $this->client()->post('/api/v2/method/logi360.api.pickup_notification', [
            'vendor_address' => $vendorAddress,
        ]);

        return $response->json('message.data');
    }
}
