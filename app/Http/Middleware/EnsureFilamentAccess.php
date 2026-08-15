<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFilamentAccess
{
    /**
     * Any recognised role may open the panel — including Scanner Staff, whose
     * Scan Station lives inside it. What they can actually see is decided per
     * resource by their abilities.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->roleEnum()) {
            abort(403, 'You do not have access to the admin panel.');
        }

        return $next($request);
    }
}
