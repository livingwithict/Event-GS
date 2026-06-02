<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\ScanLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScannerActivityExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private int $eventId,
        private ?int $dayNumber = null,
        private ?int $eventIdForDay = null,
    ) {}

    public function collection()
    {
        $query = ScanLog::with(['participant', 'actionType', 'scanner'])
            ->where('event_id', $this->eventId);

        if ($this->dayNumber && $this->eventIdForDay) {
            $event = Event::find($this->eventIdForDay);
            if ($event?->isMultiDay()) {
                $dayDate = $event->getDayDate($this->dayNumber);
                if ($dayDate) {
                    $query->whereBetween('scanned_at', [
                        $dayDate->copy()->startOfDay(),
                        $dayDate->copy()->endOfDay(),
                    ]);
                }
            }
        }

        return $query->orderByDesc('scanned_at')->get();
    }

    public function headings(): array
    {
        return ['Scanned At', 'Participant Name', 'Guest #', 'Action Type', 'Scanner Name', 'Device', 'Location'];
    }

    public function map($row): array
    {
        return [
            $row->scanned_at?->format('Y-m-d H:i:s') ?? '',
            $row->participant?->name ?? '',
            $row->participant?->guest_number ?? '',
            $row->actionType?->action_name ?? '',
            $row->scanner?->name ?? '',
            $row->scan_device ?? '',
            $row->scan_location ?? '',
        ];
    }
}
