<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Incident;
use App\Services\AlertDeduplicator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Alert $alert)
    {
        $this->onQueue('alerts');
    }

    public function handle(AlertDeduplicator $deduplicator): void
    {
        // Bind the alert's tenant so BelongsToTenant scoping/auto-fill works
        // correctly inside this queue worker process.
        app()->instance('current_tenant', $this->alert->tenant);

        if ($deduplicator->isDuplicate($this->alert->fingerprint, $this->alert->tenant_id)) {
            $this->alert->update(['status' => 'deduplicated']);

            return;
        }

        $incident = Incident::create([
            'title' => $this->alert->title,
            'severity' => $this->alert->severity,
            'status' => 'open',
        ]);

        $this->alert->update([
            'incident_id' => $incident->id,
            'status' => 'linked',
        ]);

        DispatchEscalation::dispatch($incident);
    }
}
