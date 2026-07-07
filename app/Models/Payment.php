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
        'amount_paisa', 'subtotal', 'tax_amount', 'currency', 'transaction_id',
        'invoice_number', 'gateway_txn_id', 'batch_id',
        'debit_bank_code', 'charge_amount_paisa', 'credit_status',
        'payment_status', 'paid_at', 'expires_at',
        'gateway_response', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paisa' => 'integer',
            'charge_amount_paisa' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    public function markAsRefunded(?int $verifierId = null, ?array $gatewayResponse = []): void
    {
        $this->update([
            'payment_status' => 'refunded',
            'verified_by' => $verifierId,
            'verified_at' => now(),
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse ?: []),
        ]);
    }

    public function markAsSuccess(string $gatewayTxnId, array $gatewayResponse = []): void
    {
        $this->update([
            'payment_status' => 'success',
            'gateway_txn_id' => $gatewayTxnId,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
            'invoice_number' => $this->invoice_number ?? self::generateInvoiceNumber(),
        ]);

        $this->registration->update([
            'payment_status' => 'success',
            'paid_at' => now(),
        ]);
    }

    public function recordReconciliationDetails(array $detailResponse): void
    {
        $this->update(array_filter([
            'batch_id' => isset($detailResponse['batchId']) ? (string) $detailResponse['batchId'] : null,
            'debit_bank_code' => isset($detailResponse['debitBankCode']) ? (string) $detailResponse['debitBankCode'] : null,
            'charge_amount_paisa' => isset($detailResponse['chargeAmt']) ? (int) $detailResponse['chargeAmt'] : null,
            'credit_status' => isset($detailResponse['creditStatus']) ? (string) $detailResponse['creditStatus'] : null,
            'gateway_txn_id' => isset($detailResponse['txnId']) ? (string) $detailResponse['txnId'] : $this->gateway_txn_id,
        ], fn ($v) => $v !== null));
    }

    public function isMerchantCreditSuccess(): bool
    {
        if (! $this->credit_status) {
            return true;
        }

        return in_array($this->credit_status, ['000', '999', 'DEFER'], true);
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
        $ts = strtoupper(base_convert((string) now()->timestamp, 10, 36));
        $rand = strtoupper(Str::random(7));

        return 'P'.$ts.substr($rand, 0, 7);
    }

    public static function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ym').'-'.strtoupper(Str::random(8));
    }

    public function getAmountRupees(): float
    {
        return $this->amount_paisa / 100;
    }
}
