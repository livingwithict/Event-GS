<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section heading="Before you send">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Will receive this email</div>
                <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ $this->recipientCount() }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">No email address on file</div>
                <div class="text-3xl font-bold {{ $this->withoutEmailCount() ? 'text-warning-600 dark:text-warning-400' : 'text-gray-950 dark:text-white' }}">
                    {{ $this->withoutEmailCount() }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">These guests cannot be reached by email.</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Roughly how long</div>
                <div class="text-3xl font-bold text-gray-950 dark:text-white">
                    {{ $this->recipientCount() ? ceil($this->recipientCount() * 0.6) : 0 }}s
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paced to Resend's 2 emails/second limit.</div>
            </div>
        </div>

        @if ($this->recipientCount() > 0)
            <div class="mt-6">
                <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">First few recipients</div>
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 text-sm dark:divide-white/10 dark:border-white/10">
                    @foreach ($this->recipients()->limit(5)->get() as $guest)
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="truncate text-gray-950 dark:text-white">{{ $guest->displayName() }}</span>
                            <span class="truncate text-gray-500 dark:text-gray-400">{{ $guest->email }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
