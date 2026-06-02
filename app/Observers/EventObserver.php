<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\ScanActionType;
use Illuminate\Support\Str;

class EventObserver
{
    public function saved(Event $event): void
    {
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
