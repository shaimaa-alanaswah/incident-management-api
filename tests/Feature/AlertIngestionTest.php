<?php

use App\Jobs\ProcessIncomingAlert;
use Illuminate\Support\Facades\Queue;

// Queue is faked in every test here: ingestion only ever enqueues work, and
// letting ProcessIncomingAlert actually run would touch the shared Redis
// dedup keys and make reruns within the 5-minute window flaky.
//
// POST /alerts sits behind VerifyWebhookSignature, so every ingestion request
// must carry an X-Signature header — signedAlertHeaders() computes it from
// the same payload passed to postJson().

test('valid alert returns 202', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $payload = [
        'source' => 'datadog',
        'title' => 'CPU above 90%',
        'severity' => 'P2',
        'body' => ['cpu' => 93],
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
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

    $payload = [
        'source' => 'grafana',
        'title' => 'Disk almost full',
        'severity' => 'P3',
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(202);

    Queue::assertPushedOn('alerts', ProcessIncomingAlert::class);
});

test('missing source returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $payload = [
        'title' => 'No source given',
        'severity' => 'P2',
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');
});

test('missing title returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $payload = [
        'source' => 'pingdom',
        'severity' => 'P2',
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

test('invalid severity returns 422', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $payload = [
        'source' => 'datadog',
        'title' => 'Bad severity',
        'severity' => 'P9',
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors('severity');
});

test('valid severity is accepted', function (string $severity) {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    $payload = [
        'source' => 'custom',
        'title' => "Severity {$severity} alert",
        'severity' => $severity,
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(202);
})->with(['P1', 'P2', 'P3', 'P4']);

test('unsigned alert ingestion returns 401', function () {
    Queue::fake();
    $tenant = makeTenant();
    $key = makeApiKey($tenant);

    // Valid bearer token, valid payload — but no X-Signature header.
    $this->postJson('/api/v1/alerts', [
        'source' => 'datadog',
        'title' => 'Unsigned alert',
        'severity' => 'P2',
    ], authHeaders($key))
        ->assertStatus(401)
        ->assertJsonPath('error', 'Missing webhook signature');
});

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
        $payload = [
            'source' => 'datadog',
            'title' => "Flood alert {$i}",
            'severity' => 'P4',
        ];

        $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
            ->assertStatus(202);
    }

    $payload = [
        'source' => 'datadog',
        'title' => 'Flood alert 61',
        'severity' => 'P4',
    ];

    $this->postJson('/api/v1/alerts', $payload, signedAlertHeaders($key, $payload))
        ->assertStatus(429)
        ->assertJsonPath('error', 'Rate limit exceeded')
        ->assertJsonStructure(['error', 'limit', 'retry_after']);
});
