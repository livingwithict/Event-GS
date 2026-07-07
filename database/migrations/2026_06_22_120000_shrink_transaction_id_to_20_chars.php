<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY COLUMN transaction_id VARCHAR(20)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN transaction_id TYPE VARCHAR(20)');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY COLUMN transaction_id VARCHAR(30)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN transaction_id TYPE VARCHAR(30)');
        }
    }
};
