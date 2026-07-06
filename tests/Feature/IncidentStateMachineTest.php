<?php

use App\Enums\IncidentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Services\IncidentStateMachine;

test('open to acknowledged succeeds and sets acknowledged_at', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'DB down', 'severity' => 'P1', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/acknowledge", [], authHeaders($key))
        ->assertOk()
        ->assertJsonPath('status', 'acknowledged');

    $incident->refresh();
    expect($incident->acknowledged_at)->not->toBeNull();
});

test('open to resolved succeeds for P3', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Minor glitch', 'severity' => 'P3', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [], authHeaders($key))
        ->assertOk()
        ->assertJsonPath('status', 'resolved');
});

test('open to resolved is blocked for P1', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Critical outage', 'severity' => 'P1', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonPath('error', 'Cannot transition P1 incident from [open] to [resolved] — P1/P2 must be acknowledged first');

    expect($incident->refresh()->status)->toBe(IncidentStatus::Open);
});

test('open to resolved is blocked for P2', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Major degradation', 'severity' => 'P2', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonPath('error', 'Cannot transition P2 incident from [open] to [resolved] — P1/P2 must be acknowledged first');
});

test('open to closed returns 422', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Some incident', 'severity' => 'P3', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/close", [], authHeaders($key))
        ->assertStatus(422);

    expect($incident->refresh()->status)->toBe(IncidentStatus::Open);
});

test('acknowledged to resolved succeeds and sets resolved_at', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Being handled', 'severity' => 'P1', 'status' => 'acknowledged',
        'acknowledged_at' => now(),
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [], authHeaders($key))
        ->assertOk()
        ->assertJsonPath('status', 'resolved');

    expect($incident->refresh()->resolved_at)->not->toBeNull();
});

test('closed to open throws — closed is terminal', function () {
    $tenant = makeTenant();
    $incident = $tenant->incidents()->create([
        'title' => 'Done and dusted', 'severity' => 'P3', 'status' => 'closed',
        'resolved_at' => now(), 'closed_at' => now(),
    ]);

    // No "reopen" route exists, so the terminal guard is asserted at the
    // service layer — the same code path every endpoint goes through.
    app()->instance('current_tenant', $tenant);

    expect(fn () => app(IncidentStateMachine::class)->transition($incident, IncidentStatus::Open))
        ->toThrow(InvalidStateTransitionException::class);
});

test('every successful transition writes a state log row', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Audit me', 'severity' => 'P1', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/acknowledge", [], authHeaders($key))->assertOk();
    $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [], authHeaders($key))->assertOk();
    $this->patchJson("/api/v1/incidents/{$incident->id}/close", [], authHeaders($key))->assertOk();

    $this->assertDatabaseCount('incident_state_logs', 3);
});

test('state log contains correct from_status and to_status', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $incident = $tenant->incidents()->create([
        'title' => 'Trace me', 'severity' => 'P2', 'status' => 'open',
    ]);

    $this->patchJson("/api/v1/incidents/{$incident->id}/acknowledge", ['reason' => 'on it'], authHeaders($key))->assertOk();

    $this->assertDatabaseHas('incident_state_logs', [
        'incident_id' => $incident->id,
        'tenant_id' => $tenant->id,
        'from_status' => 'open',
        'to_status' => 'acknowledged',
        'reason' => 'on it',
    ]);
});

test('state log records changed_by when an actor is provided', function () {
    $tenant = makeTenant();
    $user = $tenant->users()->create([
        'name' => 'Alice', 'email' => 'alice@example.test', 'role' => 'responder',
    ]);
    $incident = $tenant->incidents()->create([
        'title' => 'Actor test', 'severity' => 'P3', 'status' => 'open',
    ]);

    // The HTTP layer passes a null actor (API-key auth has no user identity),
    // so actor attribution is asserted through the service directly.
    app()->instance('current_tenant', $tenant);
    app(IncidentStateMachine::class)->transition($incident, IncidentStatus::Acknowledged, $user, 'taking this');

    $this->assertDatabaseHas('incident_state_logs', [
        'incident_id' => $incident->id,
        'changed_by' => $user->id,
        'to_status' => 'acknowledged',
        'reason' => 'taking this',
    ]);
});
