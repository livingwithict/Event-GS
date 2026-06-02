<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CardDeliveryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Event $event) {}

    public function collection()
    {
        return $this->event->registrations()->with(['category', 'scanLogs' => function ($query) {
            $query->whereHas('actionType', fn ($q) => $q->where('action_code', 'CARD_DELIVERY'))
                ->with('scanner');
        }])->orderBy('name')->get();
    }

    public function headings(): array
    {
        return ['Guest #', 'Name', 'Category', 'Organization', 'Email', 'Phone', 'Card Delivered', 'Delivered At', 'Delivered By'];
    }

    public function map($row): array
    {
        $cardLog = $row->scanLogs->first();

        return [
            $row->guest_number,
            $row->name,
            $row->category?->name ?? '',
            $row->organization ?? '',
            $row->email ?? '',
            $row->phone ?? '',
            $cardLog ? 'Yes' : 'No',
            $cardLog?->scanned_at?->format('Y-m-d H:i:s') ?? '',
            $cardLog?->scanner?->name ?? '',
        ];
    }
}
