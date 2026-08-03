<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ScanActionType;
use App\Models\ScanLog;

class EventDashboardController extends Controller
{
    use AuthorizesEventAccess;

    public function show(Event $event)
    {
        $this->authorizeEventAccess($event, ['finance', 'manager', 'admin', 'super_admin']);
        $day = request('day');
        $stats = $event->getStats();
        $total = $stats['total_registrations'];

        $data = [
            'total_registrations' => $total,
            'total_entries' => $stats['total_entries'],
            'lunch_used' => $stats['lunch_used'],
            'dinner_used' => $stats['dinner_used'],
            'entry_percentage' => $total > 0 ? round(($stats['total_entries'] / $total) * 100, 1) : 0,
            'lunch_percentage' => $total > 0 ? round(($stats['lunch_used'] / $total) * 100, 1) : 0,
            'dinner_percentage' => $total > 0 ? round(($stats['dinner_used'] / $total) * 100, 1) : 0,
            'card_delivery_count' => $this->getCardDeliveryCount($event),
            'card_delivery_percentage' => $total > 0 ? round(($this->getCardDeliveryCount($event) / $total) * 100, 1) : 0,
            'categories' => $event->getCategoryStats(),
            'action_stats' => $this->getActionStats($event, $day),
        ];

        if ($event->isMultiDay()) {
            $data['is_multi_day'] = true;
            $data['current_day'] = $event->getCurrentDay();
            $data['total_days'] = $event->getTotalDays();
            $data['daily_breakdown'] = $this->getDailyBreakdown($event);
        }

        return response()->json(['data' => $data]);
    }

    private function getActionStats(Event $event, ?int $day = null): array
    {
        $query = $event->scanActionTypes()->active()->ordered();

        if ($day && $event->isMultiDay()) {
            $query->where('action_code', 'LIKE', 'DAY'.$day.'_%');
        }

        return $query->get()
            ->map(function ($action) use ($event, $day) {
                $logQuery = ScanLog::where('event_id', $event->id)
                    ->where('action_type_id', $action->id);

                if ($day && $event->isMultiDay()) {
                    $dayDate = $event->getDayDate($day);
                    if ($dayDate) {
                        $logQuery->whereBetween('scanned_at', [
                            $dayDate->copy()->startOfDay(),
                            $dayDate->copy()->endOfDay(),
                        ]);
                    }
                }

                return [
                    'id' => $action->id,
                    'action_name' => $action->action_name,
                    'action_code' => $action->action_code,
                    'unique_scans' => $logQuery->distinct('participant_id')->count('participant_id'),
                ];
            })
            ->toArray();
    }

    private function getDailyBreakdown(Event $event): array
    {
        $breakdown = [];

        foreach ($event->getEventDays() as $index => $dayDate) {
            $dayNumber = $index + 1;
            $dayStart = $dayDate->copy()->startOfDay();
            $dayEnd = $dayDate->copy()->endOfDay();

            $dayActions = ScanLog::where('event_id', $event->id)
                ->whereBetween('scanned_at', [$dayStart, $dayEnd])
                ->where('action_code', 'LIKE', 'DAY'.$dayNumber.'_%')
                ->selectRaw('action_type_id, COUNT(DISTINCT participant_id) as unique_scans')
                ->groupBy('action_type_id');

            $dayActionIds = ScanActionType::where('event_id', $event->id)
                ->where('action_code', 'LIKE', 'DAY'.$dayNumber.'_%')
                ->pluck('id');

            $actionStats = ScanLog::where('event_id', $event->id)
                ->whereIn('action_type_id', $dayActionIds)
                ->whereBetween('scanned_at', [$dayStart, $dayEnd])
                ->selectRaw('action_type_id, COUNT(DISTINCT participant_id) as unique_scans')
                ->groupBy('action_type_id')
                ->pluck('unique_scans', 'action_type_id');

            $actions = ScanActionType::whereIn('id', $dayActionIds)
                ->get()
                ->map(fn ($action) => [
                    'action_code' => $action->action_code,
                    'action_name' => $action->action_name,
                    'unique_scans' => $actionStats[$action->id] ?? 0,
                ]);

            $breakdown[] = [
                'day' => $dayNumber,
                'date' => $dayDate->format('Y-m-d'),
                'action_stats' => $actions,
            ];
        }

        return $breakdown;
    }

    private function getCardDeliveryCount(Event $event): int
    {
        $cardActionId = ScanActionType::where('event_id', $event->id)
            ->where('action_code', 'CARD_DELIVERY')
            ->value('id');

        if (! $cardActionId) {
            return 0;
        }

        return ScanLog::where('event_id', $event->id)
            ->where('action_type_id', $cardActionId)
            ->distinct('participant_id')
            ->count('participant_id');
    }
}
