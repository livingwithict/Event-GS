<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_sector', function (Blueprint $table) {
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();

            $table->primary(['registration_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_sector');
    }
};
