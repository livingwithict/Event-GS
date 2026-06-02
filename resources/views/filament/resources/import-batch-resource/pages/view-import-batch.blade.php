<x-filament-panels::page>
    <x-filament-panels::header>
        @foreach ($this->getRecord()->toArray() as $key => $value)
        @endforeach
    </x-filament-panels::header>

    <div class="grid grid-cols-1 gap-4 mb-6">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-4 py-3">
                <h2 class="fi-section-header-heading text-sm font-medium text-gray-950 dark:text-white">Import Details</h2>
            </div>
            <div class="fi-section-body flex flex-col gap-y-4 px-4 pb-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">File</div>
                        <div class="font-medium">{{ $getRecord()->file_name }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Event</div>
                        <div class="font-medium">{{ $getRecord()->event?->name }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Imported By</div>
                        <div class="font-medium">{{ $getRecord()->importer?->name }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Status</div>
                        <div class="font-medium">{{ ucfirst($getRecord()->status) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Rows</div>
                        <div class="font-medium">{{ $getRecord()->total_rows }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Successful</div>
                        <div class="font-medium text-green-600">{{ $getRecord()->success_rows }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                        <div class="font-medium text-red-600">{{ $getRecord()->failed_rows }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Imported At</div>
                        <div class="font-medium">{{ $getRecord()->created_at?->format('M j, Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($getRecord()->failed_rows > 0)
    <div>
        <h3 class="text-sm font-medium text-gray-950 dark:text-white mb-3">Error Details ({{ $getRecord()->failed_rows }} rows)</h3>
        {{ $this->errorsTable }}
    </div>
    @endif
</x-filament-panels::page>
