<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_action_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('action_name', 100);
            $table->string('action_code', 50);
            $table->string('column_mapping', 50)->nullable();
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_id', 'action_code']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_action_types');
    }
};
