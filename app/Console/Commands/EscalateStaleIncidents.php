<?php

namespace App\Console\Commands;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Services\EscalationEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EscalateStaleIncidents extends Command
{
    protected $signature = 'incidents:escalate';

    protected $description = 'Evaluate open incidents against their escalation policy and notify the current step';

    public function handle(EscalationEngine $engine): void
    {
        $count = 0;

        Incident::withoutGlobalScope('tenant')
            ->where('status', IncidentStatus::Open->value)
            ->whereNotNull('escalation_policy_id')
            ->whereRaw('incidents.created_at <= DATE_SUB(NOW(), INTERVAL (
                SELECT es.delay_minutes FROM escalation_steps es
                WHERE es.escalation_policy_id = incidents.escalation_policy_id
                ORDER BY es.step_order ASC
                LIMIT 1
            ) MINUTE)')
            ->chunkById(100, function ($incidents) use ($engine, &$count) {
                foreach ($incidents as $incident) {
                    $engine->evaluate($incident);
                    $count++;
                }
            });

        Log::info("Escalation check complete: {$count} incidents evaluated");
    }
}
