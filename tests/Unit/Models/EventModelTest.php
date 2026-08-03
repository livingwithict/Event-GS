<?php

namespace Tests\Unit\Models;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_generates_slug_from_name(): void
    {
        $event = Event::factory()->create(['name' => 'ICT Conference 2025']);
        $this->assertEquals('ict-conference-2025', $event->slug);
    }

    public function test_event_has_many_registrations(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(3)->create(['event_id' => $event->id]);
        $this->assertCount(3, $event->fresh()->registrations);
    }

    public function test_event_meal_types_cast_to_array(): void
    {
        $event = Event::factory()->create(['meal_types' => ['lunch', 'dinner']]);
        $this->assertEquals(['lunch', 'dinner'], $event->meal_types);
    }

    public function test_event_stats_method(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->count(10)->create(['event_id' => $event->id]);
        Registration::factory()->count(3)->create(['event_id' => $event->id, 'entry_time' => now()]);

        $stats = $event->getStats();
        $this->assertEquals(13, $stats['total_registrations']);
        $this->assertEquals(3, $stats['total_entries']);
    }
}
