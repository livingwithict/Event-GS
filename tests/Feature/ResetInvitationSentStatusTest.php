<?php

namespace Tests\Feature;

use App\Models\Communication;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetInvitationSentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_sent_invitation_emails_but_leaves_face_verification_status_alone(): void
    {
        $event = Event::factory()->create();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'thirdfactor_status' => 'approved',
        ]);

        $invitation = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => 'invitation_face_verification',
            'status' => 'sent',
        ]);
        $faceVerification = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => 'face_verification',
            'status' => 'sent',
        ]);
        $unrelated = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => 'event_reminder',
            'status' => 'sent',
        ]);

        $this->artisan('invitations:reset-sent-status', ['event' => $event->id])
            ->expectsConfirmation(
                'Mark all 2 as superseded, so the Invitation Status column shows "Not Sent" again? Face verification status (thirdfactor_status) is not touched.',
                'yes'
            )
            ->assertSuccessful();

        $this->assertSame('superseded', $invitation->fresh()->status);
        $this->assertSame('superseded', $faceVerification->fresh()->status);
        $this->assertSame('sent', $unrelated->fresh()->status);
        $this->assertSame('approved', $registration->fresh()->thirdfactor_status);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $event = Event::factory()->create();
        $registration = Registration::factory()->create(['event_id' => $event->id]);
        $comm = Communication::create([
            'registration_id' => $registration->id,
            'type' => 'email',
            'email_type' => 'invitation',
            'status' => 'sent',
        ]);

        $this->artisan('invitations:reset-sent-status', ['event' => $event->id, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('sent', $comm->fresh()->status);
    }

    public function test_does_not_touch_communications_from_a_different_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $otherRegistration = Registration::factory()->create(['event_id' => $otherEvent->id]);

        $otherComm = Communication::create([
            'registration_id' => $otherRegistration->id,
            'type' => 'email',
            'email_type' => 'invitation',
            'status' => 'sent',
        ]);

        $this->artisan('invitations:reset-sent-status', ['event' => $event->id])
            ->assertSuccessful();

        $this->assertSame('sent', $otherComm->fresh()->status);
    }
}
