<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('salutation', 20)->nullable()->before('name');
        });

        Schema::table('import_staging', function (Blueprint $table) {
            $table->string('salutation', 20)->nullable()->before('name');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('salutation');
        });

        Schema::table('import_staging', function (Blueprint $table) {
            $table->dropColumn('salutation');
        });
    }
};
