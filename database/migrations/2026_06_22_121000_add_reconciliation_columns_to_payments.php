<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function ($table) {
            $table->string('batch_id', 50)->nullable()->after('gateway_txn_id');
            $table->string('debit_bank_code', 10)->nullable()->after('batch_id');
            $table->unsignedBigInteger('charge_amount_paisa')->nullable()->after('debit_bank_code');
            $table->string('credit_status', 10)->nullable()->after('charge_amount_paisa');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments ADD INDEX payments_batch_id_index (batch_id)');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE INDEX payments_batch_id_index ON payments (batch_id)');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments DROP INDEX payments_batch_id_index');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS payments_batch_id_index');
        }

        Schema::table('payments', function ($table) {
            $table->dropColumn(['batch_id', 'debit_bank_code', 'charge_amount_paisa', 'credit_status']);
        });
    }
};
