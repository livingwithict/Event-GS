<?php

namespace Tests\Unit\Services;

use App\Models\Registration;
use App\Services\QRCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QRCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private QRCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QRCodeService();
    }

    public function test_generate_qr_code_svg_for_registration(): void
    {
        $reg = Registration::factory()->create();

        $svg = $this->service->generateSvg($reg);

        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_qr_payload_contains_uuid(): void
    {
        $reg = Registration::factory()->create();

        $payload = $this->service->getPayload($reg);

        $this->assertEquals($reg->unique_code, $payload);
    }

    public function test_resolve_valid_code_returns_registration(): void
    {
        $reg = Registration::factory()->create();

        $found = $this->service->resolve($reg->unique_code);

        $this->assertNotNull($found);
        $this->assertEquals($reg->id, $found->id);
    }

    public function test_resolve_invalid_code_returns_null(): void
    {
        $found = $this->service->resolve('nonexistent-uuid-here');

        $this->assertNull($found);
    }
}
