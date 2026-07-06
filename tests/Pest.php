<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

/**
 * Create a tenant with sensible defaults. Override any attribute as needed.
 */
function makeTenant(array $overrides = []): Tenant
{
    return Tenant::create(array_merge([
        'name' => 'Tenant '.Str::random(6),
        'slug' => strtolower(Str::random(12)),
        'plan_tier' => 'pro',
        'is_active' => true,
    ], $overrides));
}

/**
 * Create an API key for the tenant and return the PLAINTEXT key.
 * Only the SHA-256 hash is stored, mirroring production behaviour.
 */
function makeApiKey(Tenant $tenant): string
{
    $plaintext = 'inc_test_'.Str::random(40);

    $tenant->apiKeys()->create([
        'key_hash' => hash('sha256', $plaintext),
        'key_prefix' => 'inc_test',
        'name' => 'Test Key',
    ]);

    // Tests share one real Redis with the dev environment, and tenant ids
    // restart at 1 on every suite run. Clear this tenant's current-minute
    // throttle counter so back-to-back runs within the same wall-clock
    // minute can't bleed rate-limit state into each other.
    Redis::del('rate_limit:tenant:'.$tenant->id.':'.floor(time() / 60));

    return $plaintext;
}

/**
 * Standard auth headers for a tenant-scoped API request.
 */
function authHeaders(string $plaintextKey): array
{
    return [
        'Authorization' => 'Bearer '.$plaintextKey,
        'Accept' => 'application/json',
    ];
}
