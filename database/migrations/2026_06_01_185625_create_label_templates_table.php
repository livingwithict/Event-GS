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
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('template_name');
            $table->integer('width')->default(100);
            $table->integer('height')->default(60);
            $table->boolean('show_qr')->default(true);
            $table->boolean('show_designation')->default(true);
            $table->boolean('show_organization')->default(true);
            $table->boolean('show_category_color')->default(true);
            $table->integer('font_size_name')->default(16);
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->index('event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
