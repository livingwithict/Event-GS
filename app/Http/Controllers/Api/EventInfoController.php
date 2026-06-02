<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventInfoController extends Controller
{
    public function show($eventId)
    {
        $event = Event::findOrFail($eventId);

        $eventDays = $event->getEventDays()->map(fn ($date, $index) => [
            'day_number' => $index + 1,
            'date' => $date->format('Y-m-d'),
            'label' => 'Day '.($index + 1).' - '.$date->format('M j'),
        ]);

        return response()->json([
            'data' => [
                'id' => $event->id,
                'event_name' => $event->name,
                'start_datetime' => $event->start_datetime?->toIso8601String(),
                'end_datetime' => $event->end_datetime?->toIso8601String(),
                'is_multi_day' => $event->isMultiDay(),
                'total_days' => $event->getTotalDays(),
                'current_day' => $event->getCurrentDay(),
                'event_days' => $eventDays,
            ],
        ]);
    }
}
