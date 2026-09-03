<?php

namespace Database\Factories;

use App\Models\DeliveryMean;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryMean>
 */
class DeliveryMeanFactory extends Factory
{
    protected $model = DeliveryMean::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
