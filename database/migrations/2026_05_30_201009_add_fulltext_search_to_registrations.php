<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run on PostgreSQL -- SQLite (testing) does not support tsvector/GIN
        if (DB::getDriverName() === 'pgsql') {
            // Add a generated tsvector column for full-text search
            DB::statement("
                ALTER TABLE registrations
                ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(name, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(organization, '')), 'B') ||
                    setweight(to_tsvector('english', coalesce(email, '')), 'C')
                ) STORED
            ");

            DB::statement("CREATE INDEX registrations_search_idx ON registrations USING GIN (search_vector)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS registrations_search_idx");
            DB::statement("ALTER TABLE registrations DROP COLUMN IF EXISTS search_vector");
        }
    }
};
