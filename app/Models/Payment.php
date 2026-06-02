<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'registration_id', 'event_id', 'category_id',
        'amount_paisa', 'currency', 'transaction_id',
        'gateway_txn_id', 'payment_status', 'paid_at',
        'gateway_response', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paisa' => 'integer',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_status', 'transaction_id', 'amount_paisa', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payment')
            ->setDescriptionForEvent(fn (string $eventName) => "Payment {$this->transaction_id} was {$eventName}");
    }

    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function category()
    {
        return $this->belongsTo(ParticipantCategory::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isSuccessful(): bool
    {
        return $this->payment_status === 'success';
    }

    public function isPending(): bool
    {
        return in_array($this->payment_status, ['pending', 'initiated']);
    }

    public function isFailed(): bool
    {
        return in_array($this->payment_status, ['failed', 'cancelled', 'expired']);
    }

    public function markAsSuccess(string $gatewayTxnId, array $gatewayResponse = []): void
    {
        $this->update([
            'payment_status' => 'success',
            'gateway_txn_id' => $gatewayTxnId,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
        ]);

        $this->registration->update([
            'payment_status' => 'success',
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(array $gatewayResponse = []): void
    {
        $this->update([
            'payment_status' => 'failed',
            'gateway_response' => $gatewayResponse,
        ]);
    }

    public static function generateTransactionId(): string
    {
        return 'PAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
    }

    public function getAmountRupees(): float
    {
        return $this->amount_paisa / 100;
    }
}
