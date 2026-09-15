<?php

namespace Tests\Unit\Services;

use App\Models\Registration;
use App\Services\PickAndDropService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PickAndDropServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pickndrop.base_url' => 'https://pickndrop.example.test']);
    }

    public function test_create_order_surfaces_pickndrops_own_rejection_reason(): void
    {
        Http::fake([
            'pickndrop.example.test/*' => Http::response(['message' => 'Mobile number is required'], 422),
        ]);

        $registration = Registration::factory()->create(['phone' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mobile number is required');

        app(PickAndDropService::class)->createOrder($registration);
    }

    public function test_create_order_returns_data_on_success(): void
    {
        Http::fake([
            'pickndrop.example.test/*' => Http::response([
                'data' => ['status' => 'success', 'data' => ['orderID' => 'ORD-1']],
            ], 200),
        ]);

        $registration = Registration::factory()->create();

        $data = app(PickAndDropService::class)->createOrder($registration);

        $this->assertSame('ORD-1', $data['orderID']);
    }
}
