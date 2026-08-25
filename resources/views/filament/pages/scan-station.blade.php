<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Scanner --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            <x-filament::section>
                <x-slot name="heading">Scanner</x-slot>
                <x-slot name="description">Select the checkpoint, then scan the guest's QR code.</x-slot>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="scan-event" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Event
                        </label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input.select id="scan-event" wire:model.live="eventId">
                                @foreach ($this->events() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label for="scan-action" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Scanning for
                        </label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input.select id="scan-action" wire:model.live="actionTypeId">
                                @forelse ($this->actionTypes() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @empty
                                    <option value="">No scan actions for this event</option>
                                @endforelse
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <form wire:submit="scan" class="mt-4">
                    <label for="scan-code" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                        Invitation code
                    </label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input
                            id="scan-code"
                            type="text"
                            wire:model="code"
                            autocomplete="off"
                            autofocus
                            x-ref="code"
                            x-init="$el.focus()"
                            placeholder="Scan the QR or type the code, then press Enter"
                            class="!py-4 !text-2xl !font-mono !uppercase !tracking-wider"
                        />
                    </x-filament::input.wrapper>
                </form>
            </x-filament::section>

            {{-- Result --}}
            @php
                $status = $result['status'] ?? null;
                $tone = match ($status) {
                    'ok' => ['ring-success-600/30 bg-success-50 dark:bg-success-400/10', 'text-success-700 dark:text-success-400', 'success', 'Recorded'],
                    'warning' => ['ring-warning-600/30 bg-warning-50 dark:bg-warning-400/10', 'text-warning-700 dark:text-warning-400', 'warning', 'Check'],
                    'error' => ['ring-danger-600/30 bg-danger-50 dark:bg-danger-400/10', 'text-danger-700 dark:text-danger-400', 'danger', 'Rejected'],
                    'view' => ['ring-gray-600/20 bg-gray-50 dark:bg-gray-400/10', 'text-gray-700 dark:text-gray-300', 'gray', 'Status'],
                    default => null,
                };
            @endphp

            @if ($result)
                <div class="fi-section rounded-xl shadow-sm ring-1 {{ $tone[0] }}">
                    <div class="px-6 py-5">
                        <x-filament::badge :color="$tone[2]" size="lg">{{ $tone[3] }}</x-filament::badge>

                        <h2 class="mt-3 text-3xl font-bold tracking-tight {{ $tone[1] }}">
                            {{ $result['title'] }}
                        </h2>

                        @foreach ($result['lines'] as $line)
                            <p class="mt-1 text-base text-gray-700 dark:text-gray-200">{{ $line }}</p>
                        @endforeach

                        @if (!empty($result['details']))
                            <div class="mt-4 flex flex-wrap gap-3">
                                @foreach ($result['details'] as $label => $value)
                                    <div class="rounded-lg border border-gray-950/10 dark:border-white/10 px-4 py-2.5">
                                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                        <span class="ml-1.5 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($result['can_print_label'] ?? false)
                            <x-filament::button
                                tag="a"
                                href="{{ route('labels.print-now', ['registrations' => $result['registration_id']]) }}"
                                target="_blank"
                                icon="heroicon-o-printer"
                                color="gray"
                                class="mt-4"
                            >
                                Print ID Label
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @else
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        Ready to scan.
                    </div>
                </div>
            @endif
        </div>

        {{-- Recent scans --}}
        <x-filament::section>
            <x-slot name="heading">Recent scans</x-slot>

            <ul role="list" class="-mx-6 divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($recent as $row)
                    <li class="flex items-start gap-x-3 px-6 py-3">
                        <span @class([
                            'mt-2 h-2 w-2 shrink-0 rounded-full',
                            'bg-success-500' => $row['status'] === 'ok',
                            'bg-warning-500' => $row['status'] === 'warning',
                            'bg-danger-500' => $row['status'] === 'error',
                            'bg-gray-400' => $row['status'] === 'view',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['code'] }} · {{ $row['action'] }} · {{ $row['at'] }}
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">Nothing scanned yet.</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>

    {{-- Keep the cursor in the code box: barcode wedges type wherever focus is. --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            const focus = () => document.getElementById('scan-code')?.focus();
            Livewire.hook('morphed', focus);
            document.addEventListener('click', (e) => {
                if (!e.target.closest('select, a, button')) focus();
            });
        });
    </script>
</x-filament-panels::page>
