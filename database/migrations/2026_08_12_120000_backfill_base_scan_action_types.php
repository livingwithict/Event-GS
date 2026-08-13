<?php

use App\Models\Event;
use App\Observers\EventObserver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Existing events predate the Entrance/Lunch/Dinner action types. */
    public function up(): void
    {
        Event::withoutEvents(function () {
            $observer = new EventObserver;

            Event::all()->each(fn (Event $event) => $observer->saved($event));
        });
    }

    public function down(): void
    {
        // Leaving the action types in place is harmless; scans reference them.
    }
};
