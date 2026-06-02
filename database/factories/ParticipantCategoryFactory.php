<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\ParticipantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParticipantCategory>
 */
class ParticipantCategoryFactory extends Factory
{
    protected $model = ParticipantCategory::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General Attendee', 'VIP', 'Speaker', 'Organizer', 'Volunteer', 'Media']),
            'description' => fake()->optional()->sentence(),
            'is_paid' => fake()->boolean(20),
            'price' => fake()->optional()->randomFloat(2, 500, 50000),
            'currency' => 'NPR',
            'badge_color' => fake()->optional()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
