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
        try {
            return Cache::remember('pickndrop.branches', now()->addHours(6), function () {
                $response = $this->client()->get('/api/method/logi360.api.get_branches');

                return $response->json('message.data.branches') ?? [];
            });
        } catch (\Throwable $e) {
            // A dropdown of branches isn't worth crashing the whole registration
            // form over — log it and let the field render empty instead.
            logger()->error('PickAndDrop getBranches failed: '.$e->getMessage());

            return Cache::get('pickndrop.branches', []);
        }
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
            'destinationCityArea' => $registration->destination_area,
            'codAmount' => 0,
            'orderDescription' => 'Event invitation',
            'landmark' => $registration->address,
            'ref' => (string) $registration->id,
        ]);

        // v2 endpoints wrap the payload in "data", not "message" like the v1 endpoints do.
        $data = $response->json('data');

        if (($data['status'] ?? null) !== 'success') {
            throw new \RuntimeException($data['message'] ?? 'PickAndDrop order creation failed.');
        }

        return $data['data'];
    }

    /** Tells PickAndDrop a batch is ready for their courier to collect. */
    public function createPickupRequest(string $vendorAddress): string
    {
        $response = $this->client()->post('/api/v2/method/logi360.api.pickup_notification', [
            'vendor_address' => $vendorAddress,
        ]);

        return $response->json('message.data');
    }

    /**
     * Live status + history for one order. No local status sync exists (see
     * class docblock), so callers poll this on demand instead — e.g. Scan
     * Station looking up a guest's delivery status at the moment of scan.
     */
    public function getOrderDetails(string $orderId): ?array
    {
        try {
            $response = $this->client()->get('/api/method/logi360.api.get_order_details', [
                'order_id' => $orderId,
            ]);

            return $response->json('message.data.0');
        } catch (\Throwable $e) {
            logger()->error('PickAndDrop getOrderDetails failed: '.$e->getMessage(), ['order_id' => $orderId]);

            return null;
        }
    }
}
