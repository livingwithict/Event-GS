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
        return ['Name', 'Email', 'Phone', 'Organization'];
    }

    public function map($row): array
    {
        return [$row->name, $row->email, $row->phone, $row->organization];
    }
}
