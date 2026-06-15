<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_number', 30)->nullable()->unique()->after('transaction_id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('badge_status', 20)->default('not_printed')->after('label_printed');
            $table->string('approval_status', 20)->default('approved')->after('registration_source');
        });

        Schema::table('participant_categories', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('badge_status');
            $table->dropColumn('approval_status');
        });

        Schema::table('participant_categories', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }
};
