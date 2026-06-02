<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('logo_path')->nullable()->after('venue');
            $table->string('banner_path')->nullable()->after('logo_path');
            $table->dateTime('start_datetime')->nullable()->after('banner_path');
            $table->dateTime('end_datetime')->nullable()->after('start_datetime');
            $table->dateTime('registration_open_at')->nullable()->after('end_datetime');
            $table->dateTime('registration_close_at')->nullable()->after('registration_open_at');
            $table->string('contact_info')->nullable()->after('registration_close_at');
            $table->string('status')->default('draft')->after('contact_info');

            $table->index('status');
        });

        // Migrate existing event_date values to start_datetime
        $events = DB::table('events')->whereNull('start_datetime')->whereNotNull('event_date')->get();
        foreach ($events as $event) {
            DB::table('events')->where('id', $event->id)->update([
                'start_datetime' => $event->event_date.' 00:00:00',
                'status' => 'published',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'logo_path', 'banner_path',
                'start_datetime', 'end_datetime',
                'registration_open_at', 'registration_close_at',
                'contact_info', 'status',
            ]);
        });
    }
};
