<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The nine emails that used to live in resources/views/emails — they were Digital Nepal Conclave's. */
    private const DNC_SLUG = 'digital-nepal-conclave';

    private const NAMES = [
        'invitation' => 'Invitation',
        'invitation_face_verification' => 'Invitation with face verification',
        'face_verification' => 'Face verification',
        'registration_confirmation' => 'Registration confirmation',
        'payment_success' => 'Payment success',
        'payment_failed' => 'Payment failed',
        'event_reminder' => 'Event reminder',
        'post_event_thank_you' => 'Post-event thank you',
        'urgent_update' => 'Urgent update',
    ];

    /** Subjects that used to be hardcoded in the Send Emails screens. */
    private const SUBJECTS = [
        'invitation' => 'Invitation - Digital Nepal Conclave | 20th September, 2026',
        'invitation_face_verification' => 'Invitation - Digital Nepal Conclave | 20th September, 2026',
        'urgent_update' => 'IMPORTANT NOTICE: DIGITAL NEPAL CONCLAVE 2026 POSTPONED',
    ];

    private const WITH_TICKET = ['invitation', 'invitation_face_verification', 'registration_confirmation', 'payment_success'];

    public function up(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            // 'rich' = written in the dashboard editor, 'html' = a complete HTML email with {{tags}}.
            $table->string('body_format')->default('rich');
        });

        $eventId = DB::table('events')->where('slug', self::DNC_SLUG)->value('id');

        if (! $eventId) {
            return; // fresh install or another environment: nothing to carry over
        }

        foreach (self::NAMES as $type => $name) {
            $exists = DB::table('email_drafts')->where('event_id', $eventId)->where('type', $type)->exists();
            $file = database_path("seeders/email-templates/dnc/{$type}.html");

            if ($exists || ! is_file($file)) {
                continue;
            }

            DB::table('email_drafts')->insert([
                'event_id' => $eventId,
                'name' => $name,
                'type' => $type,
                'subject' => self::SUBJECTS[$type] ?? null,
                'body' => file_get_contents($file),
                'body_format' => 'html',
                'include_qr' => false,
                'include_face_link' => false,
                'attach_ticket' => in_array($type, self::WITH_TICKET, true),
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->dropColumn('body_format');
        });
    }
};
