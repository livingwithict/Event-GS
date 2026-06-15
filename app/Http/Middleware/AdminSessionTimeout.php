<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = (int) config('session.admin_timeout', 120);
        $lastActivity = session('admin_last_activity');

        if ($lastActivity && (time() - $lastActivity) > ($timeout * 60)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.admin.auth.login')
                ->with('warning', 'Your session expired due to inactivity.');
        }

        session(['admin_last_activity' => time()]);

        return $next($request);
    }
}
