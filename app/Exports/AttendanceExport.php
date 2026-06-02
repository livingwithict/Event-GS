<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private Event $event,
        private ?int $dayNumber = null,
    ) {}

    public function collection()
    {
        $query = $this->event->registrations()->with('scanLogs.actionType');

        return $query->get();
    }

    public function headings(): array
    {
        $actionQuery = $this->event->scanActionTypes()->active()->ordered();

        if ($this->dayNumber && $this->event->isMultiDay()) {
            $actionQuery->where('action_code', 'LIKE', 'DAY'.$this->dayNumber.'_%');
        }

        $actionHeadings = $actionQuery->get()
            ->map(fn ($a) => $a->action_name.' Time')
            ->toArray();

        return ['Guest #', 'Name', 'Email', 'Phone', 'Organization', 'Designation', 'Category', 'Source', 'Payment', ...$actionHeadings];
    }

    public function map($row): array
    {
        $actionQuery = $this->event->scanActionTypes()->active()->ordered();

        if ($this->dayNumber && $this->event->isMultiDay()) {
            $actionQuery->where('action_code', 'LIKE', 'DAY'.$this->dayNumber.'_%');
        }

        $actionColumns = $actionQuery->get()
            ->map(function ($action) use ($row) {
                $logQuery = $row->scanLogs->where('action_type_id', $action->id);

                if ($this->dayNumber && $this->event->isMultiDay()) {
                    $dayDate = $this->event->getDayDate($this->dayNumber);
                    if ($dayDate) {
                        $logQuery = $logQuery->filter(fn ($l) => $l->scanned_at?->between(
                            $dayDate->copy()->startOfDay(),
                            $dayDate->copy()->endOfDay(),
                        ));
                    }
                }

                $log = $logQuery->first();

                return $log?->scanned_at?->toDateTimeString() ?? '';
            })
            ->toArray();

        return [
            $row->guest_number,
            $row->name,
            $row->email,
            $row->phone,
            $row->organization,
            $row->designation,
            $row->category?->name,
            $row->registration_source,
            $row->payment_status ?? 'N/A',
            ...$actionColumns,
        ];
    }
}
