<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner_staff']);
    }

    public function test_scan_valid_qr_returns_guest_data(): void
    {
        $reg = Registration::factory()->create();
        $this->scanner->assignedEvents()->attach($reg->event_id);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => $reg->unique_code]);

        $response->assertOk()
            ->assertJsonPath('data.name', $reg->name)
            ->assertJsonPath('data.organization', $reg->organization)
            ->assertJsonPath('data.has_entered', false);
    }

    public function test_scan_invalid_code_returns_404(): void
    {
        $response = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => '00000000-0000-0000-0000-000000000000']);

        $response->assertNotFound();
    }
}
