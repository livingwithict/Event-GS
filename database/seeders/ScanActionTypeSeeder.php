<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\ScanActionType;
use Illuminate\Database\Seeder;

class ScanActionTypeSeeder extends Seeder
{
    protected array $defaultActions = [
        ['action_name' => 'Check-In', 'action_code' => 'CHECKIN', 'column_mapping' => 'entry_time', 'allow_multiple' => false, 'sort_order' => 0],
        ['action_name' => 'Lunch', 'action_code' => 'LUNCH', 'column_mapping' => 'lunch_used_at', 'allow_multiple' => false, 'sort_order' => 1],
        ['action_name' => 'Dinner', 'action_code' => 'DINNER', 'column_mapping' => 'dinner_used_at', 'allow_multiple' => false, 'sort_order' => 2],
        ['action_name' => 'Card Delivery', 'action_code' => 'CARD_DELIVERY', 'column_mapping' => null, 'allow_multiple' => false, 'sort_order' => 3],
        ['action_name' => 'Badge Collected', 'action_code' => 'BADGE_COLLECT', 'column_mapping' => null, 'allow_multiple' => false, 'sort_order' => 4],
        ['action_name' => 'Kit Collection', 'action_code' => 'KIT_COLLECTION', 'column_mapping' => null, 'allow_multiple' => false, 'sort_order' => 5],
        ['action_name' => 'Parking Pass', 'action_code' => 'PARKING_PASS', 'column_mapping' => null, 'allow_multiple' => false, 'sort_order' => 6],
        ['action_name' => 'Certificate', 'action_code' => 'CERTIFICATE', 'column_mapping' => null, 'allow_multiple' => false, 'sort_order' => 7],
    ];

    public function run(): void
    {
        $eventId = $this->command?->option('event');

        if (! $eventId) {
            $this->command->error('Please specify an event ID: php artisan db:seed --class=ScanActionTypeSeeder --event=1');

            return;
        }

        $event = Event::find($eventId);

        if (! $event) {
            $this->command->error("Event with ID {$eventId} not found.");

            return;
        }

        foreach ($this->defaultActions as $action) {
            ScanActionType::firstOrCreate(
                ['event_id' => $event->id, 'action_code' => $action['action_code']],
                array_merge($action, ['event_id' => $event->id])
            );
        }

        $this->command->info('Seeded '.count($this->defaultActions)." scan action types for event: {$event->name}");
    }
}
