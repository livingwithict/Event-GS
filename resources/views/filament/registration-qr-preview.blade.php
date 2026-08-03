<div class="flex flex-col items-center gap-4 text-center">
    <div>
        <h2 class="text-lg font-semibold">{{ $registration->name }}</h2>
        <p class="font-mono text-sm text-gray-500">{{ $registration->guest_number }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
        {!! $qrSvg !!}
    </div>
    <p class="text-sm text-gray-500">Printable QR size: 6 × 8 inches</p>
    <a
        href="{{ route('ticket.qr-print', $registration->qr_hash) }}"
        target="_blank"
        class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
    >
        Download 6 × 8 QR PDF
    </a>
</div>
