<?php

namespace App\Services;

use App\Models\OnCallSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class OnCallResolver
{
    private int $cacheSeconds = 60;

    public function getCurrentOnCall(int $tenantId): ?User
    {
        $cacheKey = "oncall:tenant:{$tenantId}";

        return Cache::remember($cacheKey, $this->cacheSeconds, function () use ($tenantId) {
            // Explicit tenant_id + bypassed global scope: this service's contract is
            // "resolve for the given tenant" regardless of which tenant (if any) is
            // currently bound in the container.
            $schedule = OnCallSchedule::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();

            return $schedule?->user;
        });
    }
}
