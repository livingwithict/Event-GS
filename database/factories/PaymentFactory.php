<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'event_id' => Event::factory(),
            'category_id' => ParticipantCategory::factory(),
            'amount_paisa' => fake()->numberBetween(10000, 500000),
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'gateway_txn_id' => null,
            'payment_status' => 'pending',
            'paid_at' => null,
            'gateway_response' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}
