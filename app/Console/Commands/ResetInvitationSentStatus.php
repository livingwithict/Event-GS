<?php

namespace App\Console\Commands;

use App\Models\Communication;
use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Resets the "Invitation Status" column/filter back to "Not Sent" for every
 * guest of an event, ahead of a re-send (e.g. after a postponement). Only
 * touches the email send record — never registrations.thirdfactor_status,
 * so face verification completion is untouched.
 */
class ResetInvitationSentStatus extends Command
{
    protected $signature = 'invitations:reset-sent-status {event : Event ID} {--dry-run : Preview the affected count without changing anything}';

    protected $description = 'Mark an event\'s sent invitation/face-verification emails as superseded, so the Invitation Status column shows "Not Sent" again for a re-send';

    private const EMAIL_TYPES = ['invitation', 'face_verification', 'invitation_face_verification'];

    public function handle(): int
    {
        $event = Event::find($this->argument('event'));

        if (! $event) {
            $this->error("No event found with ID {$this->argument('event')}.");

            return self::FAILURE;
        }

        $query = Communication::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))
            ->where('type', 'email')
            ->whereIn('email_type', self::EMAIL_TYPES)
            ->where('status', 'sent');

        $count = $query->count();

        if ($count === 0) {
            $this->info("No sent invitation-type emails found for \"{$event->name}\". Nothing to reset.");

            return self::SUCCESS;
        }

        $this->info("Found {$count} sent email(s) (invitation / face_verification / invitation_face_verification) for \"{$event->name}\".");

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Mark all {$count} as superseded, so the Invitation Status column shows \"Not Sent\" again? Face verification status (thirdfactor_status) is not touched.")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $updated = $query->update(['status' => 'superseded']);

        $this->info("Done — {$updated} email(s) marked as superseded. Records are kept, not deleted, so send history is still visible.");

        return self::SUCCESS;
    }
}
