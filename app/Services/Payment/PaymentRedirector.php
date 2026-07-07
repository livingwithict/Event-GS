<?php

namespace App\Services\Payment;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Registration;

class PaymentRedirector
{
    public function __construct(private readonly ConnectIPSService $connectIps) {}

    public function initiate(
        Registration $reg,
        Event $event,
        ParticipantCategory $category,
        float $discountAmount = 0,
        ?PromoCode $promoCode = null,
        ?float $totalPrice = null,
    ): string {
        $originalAmount = $totalPrice ?? (float) $category->price;
        $finalAmount = max(0, $originalAmount - $discountAmount);

        $taxRate = (float) ($event->settings['tax_rate'] ?? 0);
        $taxAmount = $taxRate > 0 ? round($finalAmount * $taxRate / 100, 2) : 0;
        $totalWithTax = $finalAmount + $taxAmount;
        $amountPaisa = (int) round($totalWithTax * 100);

        $metadata = [
            'original_price' => $originalAmount,
            'discount_amount' => $discountAmount,
            'subtotal' => $finalAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $totalWithTax,
        ];

        if ($promoCode) {
            $metadata['promo_code'] = $promoCode->code;
            $metadata['discount_type'] = $promoCode->discount_type;
            $metadata['discount_value'] = $promoCode->discount_value;
            $promoCode->incrementUsage();
        }

        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'category_id' => $category->id,
            'amount_paisa' => $amountPaisa,
            'subtotal' => $finalAmount,
            'tax_amount' => $taxAmount,
            'currency' => $category->currency ?? 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'gateway_response' => $metadata,
        ]);

        return $this->connectIps->initiatePayment($payment);
    }

    public function getEffectivePrice(ParticipantCategory $category): float
    {
        if ($category->early_bird_price && $category->early_bird_until && now()->lt($category->early_bird_until)) {
            return (float) $category->early_bird_price;
        }

        return (float) $category->price;
    }
}
