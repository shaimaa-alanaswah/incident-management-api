<?php

use App\Models\TenantApiKey;

test('tenant A cannot see tenant B incidents', function () {
    $tenantA = makeTenant();
    $keyA = makeApiKey($tenantA);

    $tenantB = makeTenant();
    $tenantB->incidents()->create([
        'title' => 'Tenant B private incident',
        'severity' => 'P1',
        'status' => 'open',
    ]);

    $this->getJson('/api/v1/incidents', authHeaders($keyA))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('tenant A cannot acknowledge tenant B incident', function () {
    $tenantA = makeTenant();
    $keyA = makeApiKey($tenantA);

    $tenantB = makeTenant();
    $incidentB = $tenantB->incidents()->create([
        'title' => 'Tenant B incident',
        'severity' => 'P1',
        'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incidentB->id}/acknowledge", [], authHeaders($keyA))
        ->assertNotFound();

    // Status must be untouched.
    expect($incidentB->refresh()->status->value)->toBe('open');
});

test('tenant A cannot resolve tenant B incident', function () {
    $tenantA = makeTenant();
    $keyA = makeApiKey($tenantA);

    $tenantB = makeTenant();
    $incidentB = $tenantB->incidents()->create([
        'title' => 'Tenant B incident',
        'severity' => 'P3',
        'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incidentB->id}/resolve", [], authHeaders($keyA))
        ->assertNotFound();

    expect($incidentB->refresh()->status->value)->toBe('open');
});

test('revoked API key returns 401', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    TenantApiKey::withoutGlobalScope('tenant')
        ->where('key_hash', hash('sha256', $key))
        ->update(['revoked_at' => now()]);

    $this->getJson('/api/v1/incidents', authHeaders($key))
        ->assertUnauthorized();
});

test('expired API key returns 401', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    TenantApiKey::withoutGlobalScope('tenant')
        ->where('key_hash', hash('sha256', $key))
        ->update(['expires_at' => now()->subMinute()]);

    $this->getJson('/api/v1/incidents', authHeaders($key))
        ->assertUnauthorized();
});

test('missing API key returns 401', function () {
    $this->getJson('/api/v1/incidents')
        ->assertUnauthorized();
});

test('inactive tenant returns 401', function () {
    $tenant = makeTenant(['is_active' => false]);
    $key = makeApiKey($tenant);

    $this->getJson('/api/v1/incidents', authHeaders($key))
        ->assertUnauthorized();
});
