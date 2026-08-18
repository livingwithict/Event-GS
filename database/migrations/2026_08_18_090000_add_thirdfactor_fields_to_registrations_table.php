<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('thirdfactor_session_id')->nullable()->after('badge_status');
            $table->string('thirdfactor_verification_url')->nullable()->after('thirdfactor_session_id');
            $table->string('thirdfactor_status')->nullable()->after('thirdfactor_verification_url');
            $table->timestamp('thirdfactor_enrolled_at')->nullable()->after('thirdfactor_status');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['thirdfactor_session_id', 'thirdfactor_verification_url', 'thirdfactor_status', 'thirdfactor_enrolled_at']);
        });
    }
};
