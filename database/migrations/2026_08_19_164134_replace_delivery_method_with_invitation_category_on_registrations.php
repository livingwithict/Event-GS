<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->renameColumn('delivery_method', 'invitation_category');
        });

        DB::table('registrations')->where('invitation_category', 'self')->update(['invitation_category' => 'email_only']);
        DB::table('registrations')->where('invitation_category', 'physical_delivery')->update(['invitation_category' => 'physical_email']);

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('invitation_category')->default('email_only')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('registrations')->where('invitation_category', 'email_only')->update(['invitation_category' => 'self']);
        DB::table('registrations')->whereIn('invitation_category', ['physical_email', 'face_verification'])->update(['invitation_category' => 'physical_delivery']);

        Schema::table('registrations', function (Blueprint $table) {
            $table->renameColumn('invitation_category', 'delivery_method');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('delivery_method')->default('self')->change();
        });
    }
};
