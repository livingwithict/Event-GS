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
        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('label_printed')->default(false);
            $table->timestamp('label_printed_at')->nullable();
            $table->foreignId('label_printed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('label_printed');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['label_printed']);
            $table->dropForeign(['label_printed_by']);
            $table->dropColumn(['label_printed', 'label_printed_at', 'label_printed_by']);
        });
    }
};
