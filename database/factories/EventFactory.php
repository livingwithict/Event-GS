<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Conference ' . fake()->year(),
            'event_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'venue' => fake()->address(),
            'meal_types' => ['lunch', 'dinner'],
            'max_capacity' => fake()->optional()->numberBetween(100, 10000),
            'created_by' => User::factory(),
        ];
    }
}
