@php $batch = $this->getRecord(); @endphp

<x-filament-panels::page>
    <x-filament::section heading="Import details">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                'File' => $batch->file_name,
                'Event' => $batch->event?->name,
                'Imported by' => $batch->importer?->name,
                'Status' => ucfirst($batch->status),
                'Total rows' => $batch->total_rows,
                'Imported at' => $batch->created_at?->format('M j, Y H:i'),
            ] as $label => $value)
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="font-medium text-gray-950 dark:text-white">{{ $value ?: '—' }}</div>
                </div>
            @endforeach

            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Staged</div>
                <div class="font-medium text-success-600 dark:text-success-400">{{ $batch->success_rows }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                <div class="font-medium {{ $batch->failed_rows ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                    {{ $batch->failed_rows }}
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section :heading="'Failed rows (' . $batch->failed_rows . ')'"
                        description="Fix these in the spreadsheet and import the corrected rows again.">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
