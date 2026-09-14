<?php

use App\Models\Registration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('delivery_id', 30)->nullable()->unique()->after('guest_number');
        });

        Registration::whereNull('delivery_id')->chunkById(200, function ($registrations) {
            foreach ($registrations as $registration) {
                $registration->update(['delivery_id' => Registration::generateDeliveryId()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('delivery_id');
        });
    }
};
