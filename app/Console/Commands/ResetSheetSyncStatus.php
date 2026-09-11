<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Clears sheet_synced_at for every registration of an event, so the next
 * "Update Spreadsheet" click treats everyone as new again. Normally this
 * happens automatically when the event's Google Sheet URL is changed (see
 * Event::booted()) — this command covers the manual/force case: redoing a
 * sync to the same sheet, or fixing data left over from before that
 * automatic reset existed.
 */
class ResetSheetSyncStatus extends Command
{
    protected $signature = 'sheets:reset-sync-status {event : Event ID} {--dry-run : Preview the affected count without changing anything}';

    protected $description = 'Clear sheet_synced_at for an event\'s registrations, so the next spreadsheet sync re-sends everyone';

    public function handle(): int
    {
        $event = Event::find($this->argument('event'));

        if (! $event) {
            $this->error("No event found with ID {$this->argument('event')}.");

            return self::FAILURE;
        }

        $query = $event->registrations()->whereNotNull('sheet_synced_at');
        $count = $query->count();

        if ($count === 0) {
            $this->info("No registrations for \"{$event->name}\" are marked as synced. Nothing to reset.");

            return self::SUCCESS;
        }

        $this->info("Found {$count} registration(s) marked as already synced to the sheet for \"{$event->name}\".");

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Clear sync status for all {$count}, so the next \"Update Spreadsheet\" click appends everyone to {$event->google_sheet_id}?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $updated = $query->update(['sheet_synced_at' => null]);

        $this->info("Done — {$updated} registration(s) reset. Next \"Update Spreadsheet\" click will write the header (if missing) and append everyone.");

        return self::SUCCESS;
    }
}
