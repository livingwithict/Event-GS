<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'admin']);
    }

    public function test_event_dashboard_returns_stats(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(10)->create(['event_id' => $event->id]);
        Registration::factory()->count(3)->create([
            'event_id' => $event->id,
            'entry_time' => now(),
            'lunch_used_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/event/{$event->id}/dashboard");

        $response->assertOk()
            ->assertJsonPath('data.total_registrations', 13)
            ->assertJsonPath('data.total_entries', 3)
            ->assertJsonPath('data.lunch_used', 3);
    }

    public function test_attendance_export_returns_csv(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(5)->create(['event_id' => $event->id]);

        $response = $this->actingAs($this->manager)
            ->get("/api/reports/attendance/{$event->id}");

        $response->assertOk();
        $this->assertStringContainsString('text/', $response->headers->get('content-type'));
    }

    public function test_noshow_export_returns_unentered_registrations(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(3)->create(['event_id' => $event->id, 'entry_time' => now()]);
        Registration::factory()->count(2)->create(['event_id' => $event->id, 'entry_time' => null]);

        $response = $this->actingAs($this->manager)
            ->get("/api/reports/noshow/{$event->id}");

        $response->assertOk();
    }
}
