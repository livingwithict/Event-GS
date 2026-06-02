<?php

namespace App\Exports;

use App\Models\ParticipantCategory;
use App\Models\Registration;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategorySummaryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private int $eventId) {}

    public function collection()
    {
        return ParticipantCategory::where('event_id', $this->eventId)
            ->active()
            ->ordered()
            ->withCount('registrations')
            ->get();
    }

    public function headings(): array
    {
        return ['Category', 'Color', 'Is Paid', 'Price (NPR)', 'Registrations', 'Paid', 'Pending', 'Checked In'];
    }

    public function map($row): array
    {
        $regQuery = Registration::where('category_id', $row->id);
        $paidCount = (clone $regQuery)->where('payment_status', 'success')->count();
        $pendingCount = (clone $regQuery)->whereIn('payment_status', ['pending', 'initiated'])->count();
        $checkedIn = (clone $regQuery)->whereNotNull('entry_time')->count();

        return [
            $row->name,
            $row->badge_color,
            $row->is_paid ? 'Yes' : 'No',
            $row->is_paid ? number_format($row->price, 2) : 'Free',
            $row->registrations_count,
            $paidCount,
            $pendingCount,
            $checkedIn,
        ];
    }
}
