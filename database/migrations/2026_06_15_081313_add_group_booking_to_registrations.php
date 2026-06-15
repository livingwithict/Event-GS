<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('guest_number');
            $table->unsignedInteger('companion_count')->default(0)->after('group_id');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
            $table->dropColumn('companion_count');
        });
    }
};
