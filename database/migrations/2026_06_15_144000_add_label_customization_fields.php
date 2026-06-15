<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_templates', function (Blueprint $table) {
            $table->string('orientation')->default('portrait')->after('height');
            $table->integer('margin_top')->default(8)->after('font_size_name');
            $table->integer('margin_right')->default(8)->after('margin_top');
            $table->integer('margin_bottom')->default(8)->after('margin_right');
            $table->integer('margin_left')->default(8)->after('margin_bottom');
        });

        Schema::table('participant_categories', function (Blueprint $table) {
            $table->foreignId('label_template_id')->nullable()->after('event_id')->constrained('label_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('participant_categories', function (Blueprint $table) {
            $table->dropForeign(['label_template_id']);
            $table->dropColumn('label_template_id');
        });

        Schema::table('label_templates', function (Blueprint $table) {
            $table->dropColumn(['orientation', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left']);
        });
    }
};
