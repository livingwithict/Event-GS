<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('registration_source', 20)->default('admin_manual')->after('category_id');
            $table->string('photo_path')->nullable()->after('website');
            $table->string('meal_preference', 50)->nullable()->after('photo_path');
            $table->text('special_assistance')->nullable()->after('meal_preference');
            $table->text('notes')->nullable()->after('special_assistance');
            $table->string('pan_vat', 50)->nullable()->after('notes');
            $table->string('gender', 10)->nullable()->after('pan_vat');
            $table->timestamp('consented_at')->nullable()->after('gender');

            $table->index('registration_source');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['registration_source']);
            $table->dropColumn([
                'registration_source', 'photo_path', 'meal_preference',
                'special_assistance', 'notes', 'pan_vat', 'gender', 'consented_at',
            ]);
        });
    }
};
