<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogAuthenticationEvents
{
    public function __construct(private readonly Request $request) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        Log::channel('auth')->info('login.success', [
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $user?->email,
            'role' => $user?->role,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'guard' => $event->guard,
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::channel('auth')->warning('login.failed', [
            'email_attempted' => $event->credentials['email'] ?? null,
            'guard' => $event->guard,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'remember' => $event->credentials['remember'] ?? false,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        Log::channel('auth')->info('login.logout', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->user?->email,
            'ip' => $this->request->ip(),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
        ];
    }
}
