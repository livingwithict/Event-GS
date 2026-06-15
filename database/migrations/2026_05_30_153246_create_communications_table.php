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
        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('registration_id');
            $table->index('sent_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
