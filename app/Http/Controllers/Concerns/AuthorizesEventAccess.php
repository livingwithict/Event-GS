<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;

trait AuthorizesEventAccess
{
    protected function authorizeEventAccess(Event $event, array $roles = []): void
    {
        $user = Auth::user();

        abort_unless($user && ($roles === [] || in_array($user->role, $roles, true)), 403);
        abort_unless($user->canAccessEvent($event), 403);
    }
}
