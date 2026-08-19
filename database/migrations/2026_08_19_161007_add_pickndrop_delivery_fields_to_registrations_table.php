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
            $table->string('delivery_method')->default('self')->after('badge_status');
            $table->string('destination_branch')->nullable()->after('delivery_method');
            $table->string('pickndrop_order_id')->nullable()->after('destination_branch');
            $table->string('pickndrop_tracking_number')->nullable()->after('pickndrop_order_id');
            $table->string('pickndrop_tracking_url')->nullable()->after('pickndrop_tracking_number');
            $table->string('pickndrop_status')->nullable()->after('pickndrop_tracking_url');
            $table->timestamp('pickndrop_status_at')->nullable()->after('pickndrop_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method', 'destination_branch',
                'pickndrop_order_id', 'pickndrop_tracking_number', 'pickndrop_tracking_url',
                'pickndrop_status', 'pickndrop_status_at',
            ]);
        });
    }
};
