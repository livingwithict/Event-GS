<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'event_manager')
            ->update(['role' => 'admin']);

        DB::table('users')
            ->where('role', 'registration_staff')
            ->update(['role' => 'manager']);

        Schema::create('event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'event_manager']);

        DB::table('users')
            ->where('role', 'manager')
            ->update(['role' => 'registration_staff']);
    }
};
