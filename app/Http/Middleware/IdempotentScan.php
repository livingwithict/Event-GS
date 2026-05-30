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

        if ($requestId) {
            $lockKey = "idempotent:{$requestId}";

            if (Cache::has($lockKey)) {
                return response()->json(Cache::get($lockKey), 200);
            }
        }

        $response = $next($request);

        if ($requestId && $response->getStatusCode() === 200) {
            Cache::put("idempotent:{$requestId}", json_decode($response->getContent(), true), 5);
        }

        return $response;
    }
}
