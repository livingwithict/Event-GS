<?php

namespace App\Exports;

use App\Models\Communication;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CommunicationExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private ?string $type = null,
        private ?int $eventId = null,
    ) {}

    public function collection()
    {
        return Communication::query()
            ->with('registration.event')
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->eventId, fn ($q) => $q->whereHas('registration', fn ($q) => $q->where('event_id', $this->eventId)))
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return ['Guest Name', 'Email', 'Phone', 'Event', 'Type', 'Subject', 'Status', 'Sent At', 'Error'];
    }

    public function map($row): array
    {
        return [
            $row->registration?->name,
            $row->registration?->email,
            $row->registration?->phone,
            $row->registration?->event?->name,
            $row->type,
            $row->subject,
            $row->status,
            $row->sent_at?->toDateTimeString(),
            $row->metadata['error'] ?? '',
        ];
    }
}
