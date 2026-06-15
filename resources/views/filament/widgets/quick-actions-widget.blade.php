<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="grid gap-3">
            @foreach ($this->getActions() as $action)
                <a
                    href="{{ $action['url'] }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm transition duration-75',
                        'bg-primary-600 text-white hover:bg-primary-500' => ($action['color'] ?? 'primary') === 'primary',
                        'bg-success-600 text-white hover:bg-success-500' => ($action['color'] ?? 'primary') === 'success',
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => ($action['color'] ?? 'primary') === 'gray',
                    ])
                >
                    <x-filament::icon
                        :icon="$action['icon']"
                        class="h-5 w-5"
                    />
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
