<div class="relative"
    x-data="{
        open: false,
        events: [],
        activeEvent: {{ json_encode($activeEvent ? [
            'id' => $activeEvent->id,
            'name' => $activeEvent->name,
            'venue' => $activeEvent->venue,
            'date' => $activeEvent->start_datetime?->format('M j, Y'),
        ] : null) }},
        init() {
            fetch('{{ route('event-switcher.events') }}')
                .then(r => r.json())
                .then(data => { this.events = data; })
                .catch(() => {});
        }
    }"
    @click.outside="open = false"
>
    <button @click="open = !open"
        class="fi-sidebar-item-btn w-full flex items-center gap-3"
        :class="activeEvent ? 'fi-sidebar-item-active' : ''"
    >
        <svg class="fi-sidebar-item-icon h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
        </svg>

        <span class="fi-sidebar-item-label flex-1 text-left truncate">
            {{ $activeEvent ? $activeEvent->name : 'Select Event' }}
        </span>

        <svg class="fi-sidebar-item-icon h-4 w-4 shrink-0 text-gray-400 transition-transform"
            :class="open ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:leave="transition ease-in duration-100"
        class="absolute left-2 right-2 top-full mt-1 z-50 bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
        style="display: none;"
    >
        <div class="max-h-64 overflow-y-auto p-1">
            <template x-for="event in events" :key="event.id">
                <form method="POST" action="{{ route('event-switcher.switch') }}">
                    @csrf
                    <input type="hidden" name="event_id" :value="event.id">
                    <button type="submit"
                        class="w-full text-left rounded-md transition-colors"
                        :class="activeEvent && activeEvent.id == event.id
                            ? 'bg-primary-50 dark:bg-primary-900/20'
                            : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                    >
                        <div class="flex items-center gap-3 px-3 py-2.5">
                            <div class="h-2 w-2 rounded-full shrink-0"
                                :class="activeEvent && activeEvent.id == event.id
                                    ? 'bg-primary-500 ring-2 ring-primary-200 dark:ring-primary-800'
                                    : 'bg-gray-300 dark:bg-gray-600'">
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm truncate"
                                    :class="activeEvent && activeEvent.id == event.id
                                        ? 'font-semibold text-primary-700 dark:text-primary-300'
                                        : 'font-medium text-gray-700 dark:text-gray-300'"
                                    x-text="event.name"
                                ></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="(event.venue || '') + (event.date ? (event.venue ? ' · ' : '') + event.date : '')"></div>
                            </div>
                            <template x-if="activeEvent && activeEvent.id == event.id">
                                <svg class="h-4 w-4 shrink-0 text-primary-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </template>
                        </div>
                    </button>
                </form>
            </template>

            <template x-if="events.length === 0">
                <div class="px-4 py-6 text-center">
                    <svg class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No events available</p>
                </div>
            </template>
        </div>

        @if(Auth::user()?->isSuperAdmin() || Auth::user()?->isAdmin())
        <div class="border-t border-gray-100 dark:border-gray-800 p-1">
            <a href="{{ \App\Filament\Resources\EventResource::getUrl('create') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create Event
            </a>
        </div>
        @endif
    </div>
</div>
