<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\ScanActionType;
use Illuminate\Support\Str;

class EventObserver
{
    public function saved(Event $event): void
    {
        $this->ensureBaseActions($event);

        if (! $event->isMultiDay()) {
            $this->deactivateDayActions($event);

            return;
        }

        if (! ($event->settings['auto_generate_day_actions'] ?? true)) {
            return;
        }

        $totalDays = $event->getTotalDays();

        for ($day = 1; $day <= $totalDays; $day++) {
            $this->ensureDayAction($event->id, "DAY{$day}_CHECKIN", "Day {$day} Check-In");
            $this->ensureDayAction($event->id, "DAY{$day}_LUNCH", "Day {$day} Lunch");
            $this->ensureDayAction($event->id, "DAY{$day}_DINNER", "Day {$day} Dinner");
        }

        $this->deactivateExcessDayActions($event, $totalDays);
    }

    /**
     * Entrance / Lunch / Dinner exist for every event and carry the column they
     * stamp, so a scan updates the registration and writes a ScanLog in one place.
     */
    private function ensureBaseActions(Event $event): void
    {
        $meals = $event->meal_types ?? ['lunch', 'dinner'];

        $actions = [
            ['CHECKIN', 'Entrance', 'entry_time', 1, true],
            ['LUNCH', 'Lunch', 'lunch_used_at', 2, in_array('lunch', $meals)],
            ['DINNER', 'Dinner', 'dinner_used_at', 3, in_array('dinner', $meals)],
        ];

        foreach ($actions as [$code, $name, $column, $sort, $enabled]) {
            $action = ScanActionType::firstOrNew(['event_id' => $event->id, 'action_code' => $code]);

            $action->fill([
                'action_name' => $action->action_name ?: $name,
                'column_mapping' => $column,
                'allow_multiple' => false,
                'sort_order' => $sort,
            ]);

            // Only the meal toggles follow the event settings; never silently
            // re-enable an action an admin turned off by hand.
            if (! $action->exists) {
                $action->is_active = $enabled;
            } elseif (! $enabled) {
                $action->is_active = false;
            }

            $action->save();
        }
    }

    private function ensureDayAction(int $eventId, string $code, string $name): void
    {
        ScanActionType::firstOrCreate(
            ['event_id' => $eventId, 'action_code' => $code],
            [
                'action_name' => $name,
                'is_active' => true,
                'allow_multiple' => false,
                'sort_order' => 10 + (int) Str::after(Str::before($code, '_'), 'DAY'),
            ]
        );
    }

    private function deactivateExcessDayActions(Event $event, int $validDays): void
    {
        ScanActionType::where('event_id', $event->id)
            ->where('action_code', 'LIKE', 'DAY%')
            ->get()
            ->each(function (ScanActionType $action) use ($validDays) {
                preg_match('/^DAY(\d+)_/', $action->action_code, $matches);
                $dayNum = (int) ($matches[1] ?? 0);

                if ($dayNum > $validDays || $dayNum === 0) {
                    $action->update(['is_active' => false]);
                }
            });
    }

    private function deactivateDayActions(Event $event): void
    {
        ScanActionType::where('event_id', $event->id)
            ->where('action_code', 'LIKE', 'DAY%')
            ->update(['is_active' => false]);
    }
}
