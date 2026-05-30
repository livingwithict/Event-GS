<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = User::factory()->create(['role' => 'scanner']);
    }

    public function test_full_scan_entry_meal_flow(): void
    {
        $event = Event::factory()->create(['meal_types' => ['lunch', 'dinner']]);
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'entry_time' => null,
            'lunch_used_at' => null,
            'dinner_used_at' => null,
        ]);

        // Step 1: Scan QR
        $scanResponse = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => $reg->unique_code]);
        $scanResponse->assertOk();
        $this->assertFalse($scanResponse->json('data.has_entered'));

        // Step 2: Record entry
        $entryResponse = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);
        $entryResponse->assertOk();

        // Step 3: Attempt duplicate entry
        $dupEntryResponse = $this->actingAs($this->scanner)
            ->postJson('/api/entry', ['registration_id' => $reg->id]);
        $dupEntryResponse->assertStatus(409);

        // Step 4: Record lunch
        $lunchResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);
        $lunchResponse->assertOk();

        // Step 5: Attempt duplicate lunch
        $dupLunchResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'lunch']);
        $dupLunchResponse->assertStatus(409);

        // Step 6: Record dinner
        $dinnerResponse = $this->actingAs($this->scanner)
            ->postJson('/api/meal', ['registration_id' => $reg->id, 'meal_type' => 'dinner']);
        $dinnerResponse->assertOk();

        // Step 7: Verify dashboard stats
        $manager = User::factory()->create(['role' => 'event_manager']);
        $dashboardResponse = $this->actingAs($manager)
            ->getJson("/api/event/{$event->id}/dashboard");
        $dashboardResponse->assertOk()
            ->assertJsonPath('data.total_registrations', 1)
            ->assertJsonPath('data.total_entries', 1)
            ->assertJsonPath('data.lunch_used', 1)
            ->assertJsonPath('data.dinner_used', 1);

        // Step 8: Re-scan to verify final state
        $reScanResponse = $this->actingAs($this->scanner)
            ->postJson('/api/scan', ['code' => $reg->unique_code]);
        $reScanResponse->assertOk();
        $this->assertTrue($reScanResponse->json('data.has_entered'));
        $this->assertTrue($reScanResponse->json('data.lunch_used'));
        $this->assertTrue($reScanResponse->json('data.dinner_used'));
    }

    public function test_guest_search_finds_by_name(): void
    {
        Registration::factory()->create(['name' => 'Unique Test Name']);

        $response = $this->actingAs($this->scanner)
            ->getJson('/api/guest/search?q=Unique Test');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
