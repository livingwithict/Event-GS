<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\ParticipantCategory;
use Illuminate\Database\Seeder;

class ParticipantCategorySeeder extends Seeder
{
    protected array $defaultCategories = [
        ['name' => 'General Attendee', 'is_paid' => false, 'price' => null, 'badge_color' => '#6B7280', 'sort_order' => 0],
        ['name' => 'VIP', 'is_paid' => true, 'price' => 5000.00, 'badge_color' => '#EAB308', 'sort_order' => 1],
        ['name' => 'Chief Guest', 'is_paid' => false, 'price' => null, 'badge_color' => '#7C3AED', 'sort_order' => 2],
        ['name' => 'Organizer', 'is_paid' => false, 'price' => null, 'badge_color' => '#2563EB', 'sort_order' => 3],
        ['name' => 'Volunteer', 'is_paid' => false, 'price' => null, 'badge_color' => '#16A34A', 'sort_order' => 4],
        ['name' => 'Sponsor', 'is_paid' => false, 'price' => null, 'badge_color' => '#EA580C', 'sort_order' => 5],
        ['name' => 'Media', 'is_paid' => false, 'price' => null, 'badge_color' => '#0D9488', 'sort_order' => 6],
        ['name' => 'Speaker', 'is_paid' => false, 'price' => null, 'badge_color' => '#4F46E5', 'sort_order' => 7],
        ['name' => 'Exhibitor', 'is_paid' => true, 'price' => 10000.00, 'badge_color' => '#DC2626', 'sort_order' => 8],
    ];

    public function run(): void
    {
        $eventId = $this->command?->option('event');

        if (! $eventId) {
            $this->command->error('Please specify an event ID: php artisan db:seed --class=ParticipantCategorySeeder --event=1');

            return;
        }

        $event = Event::find($eventId);

        if (! $event) {
            $this->command->error("Event with ID {$eventId} not found.");

            return;
        }

        foreach ($this->defaultCategories as $cat) {
            ParticipantCategory::firstOrCreate(
                ['event_id' => $event->id, 'name' => $cat['name']],
                $cat
            );
        }

        $this->command->info('Seeded '.count($this->defaultCategories)." categories for event: {$event->name}");
    }
}
