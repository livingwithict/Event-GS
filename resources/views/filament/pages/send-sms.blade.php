<x-filament-panels::page>
    @unless ($this->isLive())
        <x-filament::section>
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 text-warning-500" />
                <div class="text-sm">
                    <p class="font-medium text-gray-950 dark:text-white">SMS is in log mode</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">
                        Messages are recorded in Communications but never leave the server. To send for
                        real, set <code>SMS_DRIVER=sociair</code> and a <code>SOCIAIR_SMS_TOKEN</code> in
                        the server's <code>.env</code>, then reload PHP-FPM.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endunless

    {{ $this->form }}

    @php $audience = $this->audience(); @endphp

    <x-filament::section heading="Before you send">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Will receive this text</div>
                <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ $audience['reachable'] }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">No mobile number</div>
                <div class="text-3xl font-bold {{ $audience['unreachable'] ? 'text-warning-600 dark:text-warning-400' : 'text-gray-950 dark:text-white' }}">
                    {{ $audience['unreachable'] }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Landlines and blanks are skipped.</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Message length</div>
                <div class="text-3xl font-bold text-gray-950 dark:text-white">
                    {{ mb_strlen($this->data['message'] ?? '') }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->segments() }} {{ Str::plural('part', $this->segments()) }} per guest &middot;
                    {{ $this->segments() * $audience['reachable'] }} total
                </div>
            </div>
        </div>

        @if ($audience['reachable'] > 0)
            <div class="mt-6">
                <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">First few recipients</div>
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 text-sm dark:divide-white/10 dark:border-white/10">
                    @foreach (\App\Models\Registration::whereIn('id', array_slice($this->targetIds(), 0, 5))->get() as $guest)
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="truncate text-gray-950 dark:text-white">{{ $guest->displayName() }}</span>
                            <span class="truncate font-mono text-gray-500 dark:text-gray-400">{{ $guest->phone }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
