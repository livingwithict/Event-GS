<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventSwitcherController extends Controller
{
    public function switch(Request $request)
    {
        $eventId = $request->input('event_id');
        $user = Auth::user();

        if (! $eventId) {
            session()->forget('active_event_id');

            return back();
        }

        $event = Event::findOrFail($eventId);

        if ($user->roleEnum()?->isEventScoped()) {
            $hasAccess = $user->assignedEvents()->where('event_id', $eventId)->exists();
            if (! $hasAccess) {
                abort(403, 'You do not have access to this event.');
            }
        }

        session(['active_event_id' => $event->id]);

        return back();
    }

    public function getEvents()
    {
        $user = Auth::user();

        if ($user->roleEnum()?->isEventScoped()) {
            return $user->assignedEvents()
                ->orderBy('start_datetime')
                ->get()
                ->map(fn (Event $e) => $this->formatEvent($e));
        }

        return Event::orderBy('start_datetime')
            ->get()
            ->map(fn (Event $e) => $this->formatEvent($e));
    }

    private function formatEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'registration_url' => route('register.show', ['slug' => $event->slug]),
            'venue' => $event->venue,
            'date' => $event->start_datetime?->format('M j, Y'),
            'status' => $event->status,
        ];
    }

    public static function getActiveEvent(): ?Event
    {
        $eventId = session('active_event_id');
        if (! $eventId) {
            return null;
        }

        return Event::find($eventId);
    }
}
