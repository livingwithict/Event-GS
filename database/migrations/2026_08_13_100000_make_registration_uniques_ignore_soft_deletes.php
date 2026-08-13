<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The event-scoped unique indexes counted soft-deleted rows, so deleting a guest
 * from the dashboard permanently reserved their email/phone/guest number for that
 * event: the app's own duplicate check skips trashed rows, then Postgres rejected
 * the insert. Partial indexes apply only to live rows.
 */
return new class extends Migration
{
    private array $indexes = [
        'registrations_event_email_unique' => '(event_id, email)',
        'registrations_event_phone_unique' => '(event_id, phone)',
        'registrations_event_id_guest_number_unique' => '(event_id, guest_number)',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $columns) {
            $this->drop($name);
            DB::statement("CREATE UNIQUE INDEX {$name} ON registrations {$columns} WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $name => $columns) {
            $this->drop($name);
            DB::statement(DB::getDriverName() === 'pgsql'
                ? "ALTER TABLE registrations ADD CONSTRAINT {$name} UNIQUE {$columns}"
                : "CREATE UNIQUE INDEX {$name} ON registrations {$columns}");
        }
    }

    /**
     * On Postgres these were created as table constraints, which own their index
     * and so must be dropped as constraints. SQLite only has the plain index.
     */
    private function drop(string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE registrations DROP CONSTRAINT IF EXISTS {$name}");
        }

        DB::statement("DROP INDEX IF EXISTS {$name}");
    }
};
