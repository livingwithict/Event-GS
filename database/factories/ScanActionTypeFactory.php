<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\ScanActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanActionType>
 */
class ScanActionTypeFactory extends Factory
{
    protected $model = ScanActionType::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'action_name' => fake()->randomElement(['Check-In', 'Lunch', 'Dinner', 'Card Delivery']),
            'action_code' => strtoupper(fake()->lexify('????')),
            'column_mapping' => null,
            'allow_multiple' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
