<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetSheetSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_sheet_synced_at_for_the_events_registrations(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        $synced = Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => now()]);
        $unsynced = Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => null]);

        $this->artisan('sheets:reset-sync-status', ['event' => $event->id])
            ->expectsConfirmation(
                "Clear sync status for all 1, so the next \"Update Spreadsheet\" click appends everyone to sheet-123?",
                'yes'
            )
            ->assertSuccessful();

        $this->assertNull($synced->fresh()->sheet_synced_at);
        $this->assertNull($unsynced->fresh()->sheet_synced_at);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        $registration = Registration::factory()->create(['event_id' => $event->id, 'sheet_synced_at' => now()]);

        $this->artisan('sheets:reset-sync-status', ['event' => $event->id, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertNotNull($registration->fresh()->sheet_synced_at);
    }

    public function test_does_not_touch_registrations_from_a_different_event(): void
    {
        $event = Event::factory()->create(['google_sheet_id' => 'sheet-123']);
        $otherEvent = Event::factory()->create(['google_sheet_id' => 'sheet-456']);
        $otherRegistration = Registration::factory()->create(['event_id' => $otherEvent->id, 'sheet_synced_at' => now()]);

        $this->artisan('sheets:reset-sync-status', ['event' => $event->id])
            ->assertSuccessful();

        $this->assertNotNull($otherRegistration->fresh()->sheet_synced_at);
    }
}
