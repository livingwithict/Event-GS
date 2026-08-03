<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MealUsageExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event, private ?int $day = null) {}

    public function collection()
    {
        $query = $this->event->registrations()
            ->whereNotNull('entry_time')
            ->orderBy('name');

        if ($this->day && $this->event->isMultiDay()) {
            $date = $this->event->getDayDate($this->day);
            if ($date) {
                $query->whereBetween('entry_time', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
            }
        }

        return $query->get();
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
