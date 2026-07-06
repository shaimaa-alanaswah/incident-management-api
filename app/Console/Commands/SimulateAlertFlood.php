<?php

namespace App\Console\Commands;

use App\Enums\IncidentSeverity;
use App\Jobs\ProcessIncomingAlert;
use App\Models\Alert;
use App\Models\Tenant;
use App\Services\AlertDeduplicator;
use Illuminate\Console\Command;

class SimulateAlertFlood extends Command
{
    protected $signature = 'simulate:alert-flood
                            {--tenant= : Tenant id to flood (required)}
                            {--count=100 : Number of alerts to send}
                            {--severity= : Fixed severity (P1-P4); random if omitted}';

    protected $description = 'Generate a flood of fake alerts for load testing the ingestion pipeline';

    private array $sources = ['datadog', 'grafana', 'pingdom', 'custom'];

    public function handle(AlertDeduplicator $deduplicator): int
    {
        $tenantId = $this->option('tenant');

        if (! $tenantId) {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant {$tenantId} not found.");

            return self::FAILURE;
        }

        $severityOption = $this->option('severity');

        if ($severityOption !== null && IncidentSeverity::tryFrom($severityOption) === null) {
            $this->error("Invalid severity [{$severityOption}]. Must be one of: P1, P2, P3, P4.");

            return self::FAILURE;
        }

        $count = (int) $this->option('count');

        // Bind the tenant so BelongsToTenant auto-fills tenant_id on each alert.
        app()->instance('current_tenant', $tenant);

        for ($i = 1; $i <= $count; $i++) {
            $source = $this->sources[array_rand($this->sources)];
            $severity = $severityOption ?? IncidentSeverity::cases()[array_rand(IncidentSeverity::cases())]->value;
            $title = "Simulated alert #{$i}: {$source} threshold breach";

            $alert = Alert::create([
                'source' => $source,
                'fingerprint' => $deduplicator->generateFingerprint(
                    ['source' => $source, 'title' => $title],
                    $tenant->id
                ),
                'title' => $title,
                'body' => ['simulated' => true, 'sequence' => $i],
                'severity' => $severity,
                'status' => 'new',
                'received_at' => now(),
            ]);

            ProcessIncomingAlert::dispatch($alert)->delay(now()->addSeconds(2));

            if ($i % 10 === 0) {
                $this->info("Sent {$i}/{$count} alerts...");
            }
        }

        $this->newLine();
        $this->info("Flood complete: {$count} alerts sent");
        $this->line('Check queue: php artisan queue:monitor');
        $this->line('Check logs: tail -f storage/logs/laravel.log');

        return self::SUCCESS;
    }
}
