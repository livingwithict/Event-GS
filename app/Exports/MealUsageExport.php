<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MealUsageExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()
            ->whereNotNull('entry_time')
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        $mealTypes = $this->event->meal_types ?? ['lunch', 'dinner'];

        return [
            'Name', 'Organization', 'Designation', 'Category',
            ...collect($mealTypes)->map(fn ($type) => ucfirst($type).' Used')->toArray(),
            ...collect($mealTypes)->map(fn ($type) => ucfirst($type).' Time')->toArray(),
        ];
    }

    public function map($row): array
    {
        $mealTypes = $this->event->meal_types ?? ['lunch', 'dinner'];

        $usedColumns = collect($mealTypes)->map(function ($type) use ($row) {
            $field = "{$type}_used_at";

            return $row->$field ? 'Yes' : 'No';
        })->toArray();

        $timeColumns = collect($mealTypes)->map(function ($type) use ($row) {
            $field = "{$type}_used_at";

            return $row->$field?->toDateTimeString() ?? '';
        })->toArray();

        return [
            $row->name,
            $row->organization,
            $row->designation,
            $row->category?->name,
            ...$usedColumns,
            ...$timeColumns,
        ];
    }
}
