<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_categories', function (Blueprint $table) {
            $table->decimal('early_bird_price', 10, 2)->nullable()->after('price');
            $table->timestamp('early_bird_until')->nullable()->after('early_bird_price');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->nullable()->after('amount_paisa');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('subtotal');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_amount', 'expires_at']);
        });

        Schema::table('participant_categories', function (Blueprint $table) {
            $table->dropColumn(['early_bird_price', 'early_bird_until']);
        });
    }
};
