<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private ?int $eventId = null,
        private ?string $paymentStatus = null,
    ) {}

    public function collection()
    {
        return Payment::with(['registration', 'event', 'category', 'verifier'])
            ->when($this->eventId, fn ($q) => $q->where('event_id', $this->eventId))
            ->when($this->paymentStatus, fn ($q) => $q->where('payment_status', $this->paymentStatus))
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return ['Transaction ID', 'Guest Name', 'Guest #', 'Event', 'Category', 'Amount (NPR)', 'Currency', 'Status', 'Paid At', 'Verified By'];
    }

    public function map($row): array
    {
        return [
            $row->transaction_id,
            $row->registration?->name,
            $row->registration?->guest_number,
            $row->event?->name,
            $row->category?->name,
            number_format($row->getAmountRupees(), 2),
            $row->currency,
            $row->payment_status,
            $row->paid_at?->format('Y-m-d H:i:s') ?? '',
            $row->verifier?->name ?? '',
        ];
    }
}
