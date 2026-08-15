<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\IdempotentScan;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'idempotent' => IdempotentScan::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Public traffic arrives via the nginx reverse proxy on 172.16.52.46.
        // Without this, Laravel sees the proxy's plain HTTP hop and builds
        // http:// asset URLs, which the browser then blocks on an https page.
        $middleware->trustProxies(at: [
            '172.16.52.46',
            '127.0.0.1',
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin/*')
            ? $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : route('filament.admin.auth.login')
            : route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
