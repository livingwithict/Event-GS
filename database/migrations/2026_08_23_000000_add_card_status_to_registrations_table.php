<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('card_status')->default('not_ready')->after('badge_status');
        });

        DB::table('registrations')
            ->join('invitation_categories', 'invitation_categories.id', '=', 'registrations.invitation_category_id')
            ->whereIn('invitation_categories.key', ['email_only', 'face_verification'])
            ->update(['registrations.card_status' => 'not_needed']);
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('card_status');
        });
    }
};
