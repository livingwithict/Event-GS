<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentAccess
{
    private const ALLOWED_ROLES = ['super_admin', 'event_manager', 'registration_staff', 'finance'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->user()?->role, self::ALLOWED_ROLES)) {
            abort(403, 'You do not have access to the admin panel.');
        }

        return $next($request);
    }
}
