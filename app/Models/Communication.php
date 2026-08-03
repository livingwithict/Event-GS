<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id', 'type', 'email_type', 'subject', 'content',
        'sent_at', 'status', 'provider_message_id', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeEmailType($query, string $type)
    {
        return $query->where('email_type', $type);
    }

    public function scopeRegistrationConfirmation($query)
    {
        return $query->where('email_type', 'registration_confirmation');
    }

    public function scopeEventReminder($query)
    {
        return $query->where('email_type', 'event_reminder');
    }

    public function scopePostEventThankYou($query)
    {
        return $query->where('email_type', 'post_event_thank_you');
    }

    public function markSent(?string $providerId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerId,
        ]);
    }

    public function markFailed(array $metadata = []): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }
}
