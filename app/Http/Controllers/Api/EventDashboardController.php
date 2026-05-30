<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventDashboardController extends Controller
{
    public function show(Event $event)
    {
        $stats = $event->getStats();
        $total = $stats['total_registrations'];

        return response()->json([
            'data' => [
                'total_registrations' => $total,
                'total_entries' => $stats['total_entries'],
                'lunch_used' => $stats['lunch_used'],
                'dinner_used' => $stats['dinner_used'],
                'entry_percentage' => $total > 0 ? round(($stats['total_entries'] / $total) * 100, 1) : 0,
                'lunch_percentage' => $total > 0 ? round(($stats['lunch_used'] / $total) * 100, 1) : 0,
                'dinner_percentage' => $total > 0 ? round(($stats['dinner_used'] / $total) * 100, 1) : 0,
            ],
        ]);
    }
}
