<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invitation_categories')->insert([
            'key' => 'call_email', 'name' => 'Call/Email', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('invitation_categories')->where('key', 'call_email')->delete();
    }
};
