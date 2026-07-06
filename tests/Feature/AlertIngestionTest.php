<?php

use App\Jobs\ProcessIncomingAlert;
use Illuminate\Support\Facades\Queue;

// Queue is faked in every test here: ingestion only ever enqueues work, and
// letting ProcessIncomingAlert actually run would touch the shared Redis
// dedup keys and make reruns within the 5-minute window flaky.

test('valid alert returns 202', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'source' => 'datadog',
        'title' => 'CPU above 90%',
        'severity' => 'P2',
        'body' => ['cpu' => 93],
    ], authHeaders($key))
        ->assertStatus(202)
        ->assertJsonStructure(['message', 'alert_id']);

    $this->assertDatabaseHas('alerts', [
        'tenant_id' => $tenant->id,
        'title' => 'CPU above 90%',
        'status' => 'new',
    ]);
});

test('ProcessIncomingAlert job is pushed to the alerts queue', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'source' => 'grafana',
        'title' => 'Disk almost full',
        'severity' => 'P3',
    ], authHeaders($key))->assertStatus(202);

    Queue::assertPushedOn('alerts', ProcessIncomingAlert::class);
});

test('missing source returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'title' => 'No source given',
        'severity' => 'P2',
    ], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

test('missing title returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'source' => 'pingdom',
        'severity' => 'P2',
    ], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('invalid severity returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'source' => 'datadog',
        'title' => 'Bad severity',
        'severity' => 'P9',
    ], authHeaders($key))
        ->assertStatus(422)
        ->assertJsonValidationErrors('severity');
});

test('valid severity is accepted', function (string $severity) {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $this->postJson('/api/v1/alerts', [
        'source' => 'custom',
        'title' => "Severity {$severity} alert",
        'severity' => $severity,
    ], authHeaders($key))->assertStatus(202);
})->with(['P1', 'P2', 'P3', 'P4']);

test('rate limit exceeded returns 429 for free tier', function () {
    Queue::fake();
    $tenant = makeTenant(['plan_tier' => 'free']);
    $key = makeApiKey($tenant);

    // The throttle counter is keyed on the wall-clock minute. If the minute
    // is about to roll over mid-test the 61 requests would split across two
    // counters, so wait for a fresh minute when close to the boundary.
    $secondsLeft = 60 - (time() % 60);
    if ($secondsLeft < 15) {
        sleep($secondsLeft);
        \Illuminate\Support\Facades\Redis::del(
            'rate_limit:tenant:'.$tenant->id.':'.floor(time() / 60)
        );
    }

    foreach (range(1, 60) as $i) {
        $this->postJson('/api/v1/alerts', [
            'source' => 'datadog',
            'title' => "Flood alert {$i}",
            'severity' => 'P4',
        ], authHeaders($key))->assertStatus(202);
    }

    $this->postJson('/api/v1/alerts', [
        'source' => 'datadog',
        'title' => 'Flood alert 61',
        'severity' => 'P4',
    ], authHeaders($key))
        ->assertStatus(429)
        ->assertJsonPath('error', 'Rate limit exceeded')
        ->assertJsonStructure(['error', 'limit', 'retry_after']);
});
