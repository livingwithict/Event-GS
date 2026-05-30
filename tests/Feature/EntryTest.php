<?php

use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_record_entry_for_first_time(): void
    {
        $reg = Registration::factory()->create(['entry_time' => null]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);

        $response->assertOk();
        $this->assertNotNull($reg->fresh()->entry_time);
    }

    public function test_record_entry_duplicate_returns_conflict(): void
    {
        $reg = Registration::factory()->create(['entry_time' => now()]);

        $response = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);

        $response->assertStatus(409);
    }
}
