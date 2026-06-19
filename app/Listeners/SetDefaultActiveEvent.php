<?php

namespace App\Listeners;

use App\Models\Event;
use Illuminate\Auth\Events\Login;

class SetDefaultActiveEvent
{
    public function handle(Login $event): void
    {
        if (session()->has('active_event_id')) {
            return;
        }

        $user = $event->user;

        if ($user->isManager()) {
            $firstEvent = $user->assignedEvents()->orderBy('start_datetime')->first();
        } else {
            $firstEvent = Event::orderBy('start_datetime')->first();
        }

        if ($firstEvent) {
            session(['active_event_id' => $firstEvent->id]);
        }
    }
}
