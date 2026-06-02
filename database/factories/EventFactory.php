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

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+6 months');

        return [
            'name' => fake()->company().' Conference '.fake()->year(),
            'event_date' => $start->format('Y-m-d'),
            'start_datetime' => $start,
            'end_datetime' => null,
            'venue' => fake()->address(),
            'meal_types' => ['lunch', 'dinner'],
            'max_capacity' => fake()->optional()->numberBetween(100, 10000),
            'status' => 'published',
            'created_by' => User::factory(),
        ];
    }
}
