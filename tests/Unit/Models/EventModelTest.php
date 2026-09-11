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

    public function test_extract_google_sheet_id_from_full_url(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1AbC-dEf_23XYZ/edit#gid=0';
        $this->assertEquals('1AbC-dEf_23XYZ', Event::extractGoogleSheetId($url));
    }

    public function test_extract_google_sheet_id_from_bare_id(): void
    {
        $this->assertEquals('1AbC-dEf_23XYZ', Event::extractGoogleSheetId('1AbC-dEf_23XYZ'));
    }

    public function test_extract_google_sheet_id_returns_null_for_blank_input(): void
    {
        $this->assertNull(Event::extractGoogleSheetId(''));
        $this->assertNull(Event::extractGoogleSheetId(null));
    }

    public function test_changing_the_google_sheet_resets_sync_progress_for_its_registrations(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-old']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'sheet_synced_at' => now(),
        ]);

        $event->update(['google_sheet_id' => 'sheet-new']);

        $this->assertNull($registration->fresh()->sheet_synced_at);
    }

    public function test_unrelated_event_updates_do_not_reset_sync_progress(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        $syncedAt = now();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'sheet_synced_at' => $syncedAt,
        ]);

        $event->update(['venue' => 'A different venue']);

        $this->assertNotNull($registration->fresh()->sheet_synced_at);
    }

    public function test_changing_only_the_tab_gid_also_resets_sync_progress(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123', 'google_sheet_tab_gid' => '111']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'sheet_synced_at' => now(),
        ]);

        $event->update(['google_sheet_tab_gid' => '222']);

        $this->assertNull($registration->fresh()->sheet_synced_at);
    }

    public function test_extract_google_sheet_tab_gid_from_url(): void
    {
        $url = 'https://docs.google.com/spreadsheets/d/1TNli1IVBqF1N-rKF3W6ggtE_RULNtEXFxM7-5_tfzWY/edit?gid=1651722043#gid=1651722043';
        $this->assertEquals('1651722043', Event::extractGoogleSheetTabGid($url));
    }

    public function test_extract_google_sheet_tab_gid_returns_null_when_absent(): void
    {
        $this->assertNull(Event::extractGoogleSheetTabGid('1AbC-dEf_23XYZ'));
        $this->assertNull(Event::extractGoogleSheetTabGid(''));
        $this->assertNull(Event::extractGoogleSheetTabGid(null));
    }
}
