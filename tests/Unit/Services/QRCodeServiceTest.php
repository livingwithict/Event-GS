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
        $this->service = new QRCodeService;
    }

    public function test_generate_qr_code_svg_for_registration(): void
    {
        $reg = Registration::factory()->create();

        $svg = $this->service->generateSvg($reg);

        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_qr_payload_is_the_plain_invitation_code(): void
    {
        $reg = Registration::factory()->create();

        $this->assertSame($reg->guest_number, $this->service->getPayload($reg));
    }

    public function test_resolve_valid_uuid_returns_registration(): void
    {
        $reg = Registration::factory()->create();

        $found = $this->service->resolve($reg->unique_code);

        $this->assertNotNull($found);
        $this->assertEquals($reg->id, $found->id);
    }

    public function test_resolve_checkin_url_returns_registration(): void
    {
        $reg = Registration::factory()->create();
        $url = $this->service->getPayload($reg);

        $found = $this->service->resolve($url);

        $this->assertNotNull($found);
        $this->assertEquals($reg->id, $found->id);
    }

    public function test_resolve_token_directly_returns_registration(): void
    {
        $reg = Registration::factory()->create();

        $found = $this->service->resolveFromToken($reg->qr_hash);

        $this->assertNotNull($found);
        $this->assertEquals($reg->id, $found->id);
    }

    public function test_resolve_invalid_code_returns_null(): void
    {
        $found = $this->service->resolve('nonexistent-uuid-here');

        $this->assertNull($found);
    }
}
