<?php

use App\Models\EscalationPolicy;

function makePolicyPayload(): array
{
    return [
        'name' => 'Test Policy',
        'repeat_count' => 0,
        'steps' => [
            ['step_order' => 1, 'delay_minutes' => 5, 'notify_user_id' => null, 'notify_channel' => 'email'],
            ['step_order' => 2, 'delay_minutes' => 10, 'notify_user_id' => null, 'notify_channel' => 'slack'],
        ],
    ];
}

test('creating escalation policy with valid steps returns 201', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/escalation-policies', makePolicyPayload(), authHeaders($key))
        ->assertCreated()
        ->assertJsonPath('name', 'Test Policy')
        ->assertJsonCount(2, 'steps');
});

test('creating policy with no steps returns 422', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/escalation-policies', [
        'name' => 'Stepless Policy',
        'repeat_count' => 0,
    ], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonValidationErrors('steps');
});

test('deleting policy with no active incidents succeeds', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $policy = $tenant->escalationPolicies()->create(['name' => 'Deletable', 'repeat_count' => 0]);

    // Implementation returns 204 No Content (not 200) on success.
    $this->deleteJson("/api/v1/escalation-policies/{$policy->id}", [], authHeaders($key))
        ->assertNoContent();

    $this->assertSoftDeleted('escalation_policies', ['id' => $policy->id]);
});

test('deleting policy referenced by open incident returns 409', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $policy = $tenant->escalationPolicies()->create(['name' => 'In use', 'repeat_count' => 0]);
    $tenant->incidents()->create([
        'title' => 'Open incident', 'severity' => 'P1', 'status' => 'open',
        'escalation_policy_id' => $policy->id,
    ]);

    $this->deleteJson("/api/v1/escalation-policies/{$policy->id}", [], authHeaders($key))
        ->assertStatus(409);

    $this->assertNotSoftDeleted('escalation_policies', ['id' => $policy->id]);
});

test('deleting policy referenced by acknowledged incident returns 409', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $policy = $tenant->escalationPolicies()->create(['name' => 'Still in use', 'repeat_count' => 0]);
    $tenant->incidents()->create([
        'title' => 'Acked incident', 'severity' => 'P2', 'status' => 'acknowledged',
        'acknowledged_at' => now(), 'escalation_policy_id' => $policy->id,
    ]);

    $this->deleteJson("/api/v1/escalation-policies/{$policy->id}", [], authHeaders($key))
        ->assertStatus(409);
});

test('deleting policy referenced only by resolved incident succeeds', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $policy = $tenant->escalationPolicies()->create(['name' => 'History only', 'repeat_count' => 0]);
    $tenant->incidents()->create([
        'title' => 'Resolved incident', 'severity' => 'P3', 'status' => 'resolved',
        'resolved_at' => now(), 'escalation_policy_id' => $policy->id,
    ]);

    // Resolved is not "active" — deletion is allowed (204 No Content).
    $this->deleteJson("/api/v1/escalation-policies/{$policy->id}", [], authHeaders($key))
        ->assertNoContent();

    $this->assertSoftDeleted('escalation_policies', ['id' => $policy->id]);
});

test('oncall endpoint returns 404 when no active schedule', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->getJson('/api/v1/schedules/oncall', authHeaders($key))
        ->assertNotFound()
        ->assertJsonPath('message', 'Nobody is currently on call');
});

test('oncall endpoint returns the on-call user when an active schedule exists', function () {
    $tenant = makeTenant();
    $key = makeApiKey($tenant);
    $user = $tenant->users()->create([
        'name' => 'Oncall Olive', 'email' => 'olive@example.test', 'role' => 'responder',
    ]);
    $tenant->onCallSchedules()->create([
        'name' => 'Primary', 'user_id' => $user->id,
        'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
    ]);

    $this->getJson('/api/v1/schedules/oncall', authHeaders($key))
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('name', 'Oncall Olive');
});
