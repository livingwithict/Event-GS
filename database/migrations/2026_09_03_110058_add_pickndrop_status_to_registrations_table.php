<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('pickndrop_status')->nullable()->after('pickndrop_tracking_url');
            $table->timestamp('pickndrop_status_checked_at')->nullable()->after('pickndrop_status');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['pickndrop_status', 'pickndrop_status_checked_at']);
        });
    }
};
