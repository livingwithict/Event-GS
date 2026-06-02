<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('action_type_id')->constrained('scan_action_types')->cascadeOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scan_device')->nullable();
            $table->string('scan_location')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['event_id', 'participant_id', 'action_type_id']);
            $table->index('scanned_by');
            $table->index('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
