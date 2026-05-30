<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()->get();
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Organization', 'Designation', 'Entry Time', 'Lunch Used At', 'Dinner Used At'];
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->phone,
            $row->organization,
            $row->designation,
            $row->entry_time?->toDateTimeString(),
            $row->lunch_used_at?->toDateTimeString(),
            $row->dinner_used_at?->toDateTimeString(),
        ];
    }
}
