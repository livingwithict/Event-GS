<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('mail_domain')->nullable();
            // Encrypted by the model cast; ciphertext is much longer than the key.
            $table->text('resend_api_key')->nullable();
        });

        Schema::create('email_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // One of the built-in template types, or 'custom' (body written in the dashboard).
            $table->string('type')->default('custom');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('include_qr')->default(true);
            $table->boolean('include_face_link')->default(false);
            $table->boolean('attach_ticket')->default(false);
            // The draft automatic emails of this type use for the event.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_drafts');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['mail_domain', 'resend_api_key']);
        });
    }
};
