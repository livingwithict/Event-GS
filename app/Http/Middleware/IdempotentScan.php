<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotentScan
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');

        if (! $requestId || ! preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $requestId)) {
            return $next($request);
        }

        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        $lockKey = 'idempotent:'.hash('sha256', implode('|', [
            $userId,
            $request->route()?->getName() ?? $request->path(),
            $requestId,
            hash('sha256', $request->getContent()),
        ]));

        return Cache::lock($lockKey.':lock', 10)->block(5, function () use ($request, $next, $lockKey) {
            if ($cached = Cache::get($lockKey)) {
                return response($cached['content'], $cached['status'], $cached['headers']);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($lockKey, [
                    'content' => $response->getContent(),
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                ], now()->addMinutes(5));
            }

            return $response;
        });
    }
}
