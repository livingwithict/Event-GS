<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;

trait HasEventScope
{
    protected static function getActiveEventId(): ?int
    {
        return session('active_event_id');
    }

    protected static function getActiveEvent(): ?Event
    {
        $id = static::getActiveEventId();

        if (! $id) {
            return null;
        }

        return Event::find($id);
    }

    protected static function scopeByActiveEvent($query): void
    {
        $eventId = static::getActiveEventId();

        if (! $eventId) {
            return;
        }

        $user = Auth::user();

        if ($user?->roleEnum()?->isEventScoped()) {
            $hasAccess = $user->assignedEvents()->where('event_id', $eventId)->exists();
            if (! $hasAccess) {
                return;
            }
        }

        $query->where('event_id', $eventId);
    }
}
