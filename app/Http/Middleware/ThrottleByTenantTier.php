<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class ThrottleByTenantTier
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app('current_tenant');

        if (! $tenant) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $limits = config('tenant.rate_limits');
        $limit = $limits[$tenant->plan_tier] ?? $limits['free'];

        $key = "rate_limit:tenant:{$tenant->id}:".floor(time() / 60);

        $current = Redis::incr($key);
        Redis::expire($key, 60);

        if ($current > $limit) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'limit' => $limit,
                'retry_after' => 60 - (time() % 60),
            ], 429);
        }

        return $next($request);
    }
}