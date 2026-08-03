<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unique(['event_id', 'email'], 'registrations_event_email_unique');
            $table->unique(['event_id', 'phone'], 'registrations_event_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique('registrations_event_email_unique');
            $table->dropUnique('registrations_event_phone_unique');
        });
    }
};
