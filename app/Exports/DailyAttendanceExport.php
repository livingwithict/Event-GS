<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\ScanActionType;
use App\Models\ScanLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DailyAttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->getEventDays()->map(fn ($date, $index) => [
            'day' => $index + 1,
            'date' => $date,
        ]);
    }

    public function headings(): array
    {
        $actions = $this->getDayActionCodes();

        return ['Day', 'Date', ...$actions];
    }

    public function map($row): array
    {
        $dayNumber = $row['day'];
        $dayDate = $row['date'];
        $counts = $this->getCountsForDay($dayNumber, $dayDate);

        return [
            'Day '.$dayNumber,
            $dayDate->format('Y-m-d'),
            ...$counts,
        ];
    }

    private function getDayActionCodes(): array
    {
        return ScanActionType::where('event_id', $this->event->id)
            ->where('action_code', 'LIKE', 'DAY%')
            ->active()
            ->orderBy('action_code')
            ->get()
            ->filter(fn ($a) => preg_match('/^DAY\d+_/', $a->action_code))
            ->map(fn ($a) => $a->action_name)
            ->unique()
            ->values()
            ->toArray();
    }

    private function getCountsForDay(int $dayNumber, $dayDate): array
    {
        $actionCodes = ScanActionType::where('event_id', $this->event->id)
            ->where('action_code', 'LIKE', 'DAY'.$dayNumber.'_%')
            ->active()
            ->pluck('id');

        $scans = ScanLog::where('event_id', $this->event->id)
            ->whereIn('action_type_id', $actionCodes)
            ->whereBetween('scanned_at', [
                $dayDate->copy()->startOfDay(),
                $dayDate->copy()->endOfDay(),
            ])
            ->selectRaw('action_type_id, COUNT(DISTINCT participant_id) as count')
            ->groupBy('action_type_id')
            ->pluck('count', 'action_type_id');

        return ScanActionType::where('event_id', $this->event->id)
            ->where('action_code', 'LIKE', 'DAY'.$dayNumber.'_%')
            ->active()
            ->orderBy('action_code')
            ->get()
            ->map(fn ($a) => $scans[$a->id] ?? 0)
            ->toArray();
    }
}
