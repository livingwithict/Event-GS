<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->uuid('unique_code')->unique();
            $table->string('qr_hash')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('organization')->nullable();
            $table->string('designation')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->timestamp('entry_time')->nullable();
            $table->timestamp('lunch_used_at')->nullable();
            $table->timestamp('dinner_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('email');
            $table->index('phone');
            $table->index('organization');
            $table->index('qr_hash');
            $table->index('entry_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
