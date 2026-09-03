<x-filament::button
    type="button"
    color="gray"
    size="sm"
    icon="heroicon-o-arrow-path"
    wire:click="refreshAllStatuses"
    wire:confirm="Fetch live status for every guest here with a delivery order? This can take a moment for a large list."
    wire:loading.attr="disabled"
    wire:target="refreshAllStatuses"
>
    Refresh Statuses
</x-filament::button>
