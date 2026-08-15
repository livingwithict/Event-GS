<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;

trait AuthorizesEventAccess
{
    /**
     * Two questions, both of which must pass: may this role do the thing, and is
     * this one of their events. Callers name the ability, never the roles.
     */
    protected function authorizeEventAccess(Event $event, string $ability): void
    {
        $user = Auth::user();

        abort_unless($user && $user->hasAbility($ability), 403);
        abort_unless($user->canAccessEvent($event), 403);
    }
}
