<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Colleagues routinely register under one shared organisation email or phone,
 * so contact details cannot identify a guest. The invitation code does that —
 * (event_id, guest_number) stays unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['registrations_event_email_unique', 'registrations_event_phone_unique'] as $name) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE registrations DROP CONSTRAINT IF EXISTS {$name}");
            }

            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    public function down(): void
    {
        DB::statement('CREATE UNIQUE INDEX registrations_event_email_unique ON registrations (event_id, email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX registrations_event_phone_unique ON registrations (event_id, phone) WHERE deleted_at IS NULL');
    }
};
