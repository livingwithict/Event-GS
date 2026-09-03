<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Scanner --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            <x-filament::section>
                <x-slot name="heading">Scanner</x-slot>
                <x-slot name="description">Select the delivery means, then scan the guest's QR code.</x-slot>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="track-event" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Event
                        </label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input.select id="track-event" wire:model.live="eventId">
                                @foreach ($this->events() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label for="track-mean" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Delivery means
                        </label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input.select id="track-mean" wire:model.live="deliveryMeanId">
                                @forelse ($this->deliveryMeans() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @empty
                                    <option value="">No delivery means yet — add one above</option>
                                @endforelse
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <form wire:submit="scan" class="mt-4">
                    <label for="track-code" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                        Invitation code
                    </label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input
                            id="track-code"
                            type="text"
                            wire:model="code"
                            autocomplete="off"
                            autofocus
                            placeholder="Scan the QR or type the code, then press Enter"
                            class="!py-4 !text-2xl !font-mono !uppercase !tracking-wider"
                        />
                    </x-filament::input.wrapper>
                </form>
            </x-filament::section>

            @php
                $status = $result['status'] ?? null;
                $tone = match ($status) {
                    'ok' => ['ring-success-600/30 bg-success-50 dark:bg-success-400/10', 'text-success-700 dark:text-success-400', 'success', 'Assigned'],
                    'error' => ['ring-danger-600/30 bg-danger-50 dark:bg-danger-400/10', 'text-danger-700 dark:text-danger-400', 'danger', 'Rejected'],
                    default => null,
                };
            @endphp

            @if ($result)
                <div class="fi-section rounded-xl shadow-sm ring-1 {{ $tone[0] }}">
                    <div class="px-6 py-5">
                        <x-filament::badge :color="$tone[2]" size="lg">{{ $tone[3] }}</x-filament::badge>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight {{ $tone[1] }}">{{ $result['title'] }}</h2>
                        @foreach ($result['lines'] as $line)
                            <p class="mt-1 text-base text-gray-700 dark:text-gray-200">{{ $line }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Means lookup --}}
            <x-filament::section>
                <x-slot name="heading">Delivery means lookup</x-slot>
                <x-slot name="description">Pick a delivery means to see its description and who has been assigned to it.</x-slot>

                @if ($lookupMeanId)
                    <x-slot name="afterHeader">
                        <x-filament::button wire:click="exportMeanGuests" icon="heroicon-o-arrow-down-tray" color="gray" size="sm">
                            Export CSV
                        </x-filament::button>
                    </x-slot>
                @endif

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="lookupMeanId">
                        <option value="">Select a delivery means…</option>
                        @foreach ($this->deliveryMeans() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @if ($lookupMeanId)
                    @if ($lookupMeanDescription)
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $lookupMeanDescription }}</p>
                    @endif

                    <ul role="list" class="mt-3 divide-y divide-gray-100 dark:divide-white/10 rounded-lg border border-gray-200 dark:border-white/10">
                        @forelse ($lookupMeanGuests as $guest)
                            <li class="px-4 py-2.5">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $guest['label'] }}</span>
                                <span class="ml-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $guest['code'] }}</span>
                            </li>
                        @empty
                            <li class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">No guests assigned yet.</li>
                        @endforelse
                    </ul>
                @endif
            </x-filament::section>

            {{-- Guest search --}}
            <x-filament::section>
                <x-slot name="heading">Find a guest</x-slot>
                <x-slot name="description">Search by name to see whether a card has been assigned.</x-slot>

                <div class="relative">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model.live.debounce.300ms="nameQuery"
                            autocomplete="off"
                            placeholder="Start typing a guest's name…"
                        />
                    </x-filament::input.wrapper>

                    @if (!empty($nameResults))
                        <ul role="list" class="mt-3 divide-y divide-gray-100 dark:divide-white/10 rounded-lg border border-gray-200 dark:border-white/10">
                            @foreach ($nameResults as $match)
                                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                    <div>
                                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $match['label'] }}</span>
                                        <span class="ml-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $match['code'] }}</span>
                                    </div>
                                    @if ($match['delivery_mean'])
                                        <x-filament::badge color="success">{{ $match['delivery_mean'] }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">Unassigned</x-filament::badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-filament::section>
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
                            'bg-danger-500' => $row['status'] === 'error',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['code'] }} · {{ $row['mean'] }} · {{ $row['at'] }}
                            </p>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">Nothing scanned yet.</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>

    {{-- Manage Delivery Means modal --}}
    <x-filament::modal id="manage-delivery-means" width="lg" close-button>
        <x-slot name="heading">Manage Delivery Means</x-slot>

        <div class="flex flex-col gap-4">
            <ul role="list" class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->deliveryMeans() as $id => $name)
                    <li class="py-3">
                        @if ($confirmingDeleteMeanId === $id)
                            <div class="rounded-lg bg-danger-50 p-3 text-sm dark:bg-danger-400/10">
                                <p class="text-danger-700 dark:text-danger-400">
                                    Delete "{{ $name }}"?
                                    @if ($confirmingDeleteMeanGuestCount > 0)
                                        {{ $confirmingDeleteMeanGuestCount }} guest(s) will lose this assignment.
                                    @endif
                                </p>
                                <div class="mt-2 flex gap-2">
                                    <x-filament::button size="sm" color="danger" wire:click="confirmDeleteMean">Delete</x-filament::button>
                                    <x-filament::button size="sm" color="gray" wire:click="cancelDeleteMean">Cancel</x-filament::button>
                                </div>
                            </div>
                        @elseif ($editingMeanId === $id)
                            <div class="flex flex-col gap-2">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="editMeanName" placeholder="Name" />
                                </x-filament::input.wrapper>
                                <x-filament::input.wrapper>
                                    <x-filament::input type="text" wire:model="editMeanDescription" placeholder="Description" />
                                </x-filament::input.wrapper>
                                <div class="flex gap-2">
                                    <x-filament::button size="sm" wire:click="saveEditMean">Save</x-filament::button>
                                    <x-filament::button size="sm" color="gray" wire:click="cancelEditMean">Cancel</x-filament::button>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $name }}</span>
                                <div class="flex gap-2">
                                    <x-filament::icon-button icon="heroicon-o-pencil-square" wire:click="startEditMean({{ $id }})" label="Edit" />
                                    <x-filament::icon-button icon="heroicon-o-trash" color="danger" wire:click="requestDeleteMean({{ $id }})" label="Delete" />
                                </div>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-3 text-sm text-gray-500 dark:text-gray-400">No delivery means yet.</li>
                @endforelse
            </ul>

            <div class="flex flex-col gap-2 border-t border-gray-100 pt-4 dark:border-white/10">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="newMeanName" placeholder="New delivery means name" />
                </x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="newMeanDescription" placeholder="Description (optional)" />
                </x-filament::input.wrapper>
                <x-filament::button wire:click="createMean">Add</x-filament::button>
            </div>
        </div>
    </x-filament::modal>
</x-filament-panels::page>
