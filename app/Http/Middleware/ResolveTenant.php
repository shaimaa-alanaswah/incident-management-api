<?php

namespace App\Http\Middleware;

use App\Models\TenantApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->bearerToken();

        if (! $rawKey) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $keyHash = hash('sha256', $rawKey);

        $apiKey = TenantApiKey::with('tenant')
            ->where('key_hash', $keyHash)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $apiKey || ! $apiKey->tenant || ! $apiKey->tenant->is_active) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Bind tenant to the container — picked up by BelongsToTenant's global scope.
        app()->instance('current_tenant', $apiKey->tenant);

        // Update last_used_at without touching updated_at or firing model events.
        TenantApiKey::withoutTimestamps(function () use ($apiKey) {
            $apiKey->update(['last_used_at' => now()]);
        });

        return $next($request);
    }
}
