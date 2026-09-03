<?php

namespace App\Filament\Resources\LogisticsResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\LogisticsResource;
use App\Models\Registration;
use App\Services\PickAndDropService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLogistics extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = LogisticsResource::class;

    /** Fetches live courier status for every guest in the current event with a delivery order. */
    public function refreshAllStatuses(): void
    {
        $service = app(PickAndDropService::class);
        $eventId = session('active_event_id');

        $registrations = Registration::query()
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId))
            ->whereNotNull('pickndrop_order_id')
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($registrations as $registration) {
            $details = $service->getOrderDetails($registration->pickndrop_order_id);

            if (! $details) {
                $failed++;

                continue;
            }

            $registration->update([
                'pickndrop_status' => $details['status'] ?? 'Unknown',
                'pickndrop_status_checked_at' => now(),
            ]);
            $updated++;
        }

        Notification::make()
            ->success()
            ->title("Refreshed {$updated} statuses".($failed ? ", {$failed} failed" : ''))
            ->send();
    }
}
