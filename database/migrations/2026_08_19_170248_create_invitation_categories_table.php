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
        Schema::create('invitation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Fixed set, not user-managed: the three ways a guest's invitation goes out.
        DB::table('invitation_categories')->insert([
            ['key' => 'physical_email', 'name' => 'Physical/Email', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'email_only', 'name' => 'Email only', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification', 'name' => 'Face Verification', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_categories');
    }
};
