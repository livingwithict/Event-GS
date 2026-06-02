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
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data')->nullable();
            $table->string('error_message', 500);
            $table->timestamp('created_at')->nullable();

            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
