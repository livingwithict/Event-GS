<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->date('scan_date')->nullable()->after('scanned_at')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE scan_logs SET scan_date = scanned_at::date WHERE scan_date IS NULL');
        } else {
            DB::statement('UPDATE scan_logs SET scan_date = DATE(scanned_at) WHERE scan_date IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex('scan_logs_scan_date_index');
            $table->dropColumn('scan_date');
        });
    }
};
