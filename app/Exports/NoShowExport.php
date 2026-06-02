<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NoShowExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()->whereNull('entry_time')->get();
    }

    public function headings(): array
    {
        return ['Guest #', 'Name', 'Email', 'Phone', 'Organization', 'Category', 'Source'];
    }

    public function map($row): array
    {
        return [$row->guest_number, $row->name, $row->email, $row->phone, $row->organization, $row->category?->name, $row->registration_source];
    }
}
