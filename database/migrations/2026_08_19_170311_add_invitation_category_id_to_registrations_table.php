<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('invitation_category_id')->nullable()->after('invitation_category')
                ->constrained()->nullOnDelete();
        });

        $ids = DB::table('invitation_categories')->pluck('id', 'key');

        foreach (['physical_email', 'email_only', 'face_verification'] as $key) {
            if (isset($ids[$key])) {
                DB::table('registrations')->where('invitation_category', $key)->update(['invitation_category_id' => $ids[$key]]);
            }
        }
        DB::table('registrations')->whereNull('invitation_category_id')->update(['invitation_category_id' => $ids['email_only']]);

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('invitation_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('invitation_category')->default('email_only')->after('badge_status');
        });

        $keys = DB::table('invitation_categories')->pluck('key', 'id');
        foreach (DB::table('registrations')->whereNotNull('invitation_category_id')->get(['id', 'invitation_category_id']) as $row) {
            DB::table('registrations')->where('id', $row->id)->update(['invitation_category' => $keys[$row->invitation_category_id] ?? 'email_only']);
        }

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invitation_category_id');
        });
    }
};
