<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Services\OnCallResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchEscalation implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Incident $incident)
    {
        $this->onQueue('escalations');
    }

    public function uniqueId(): string
    {
        return "incident:{$this->incident->id}";
    }

    public function handle(OnCallResolver $onCallResolver): void
    {
        app()->instance('current_tenant', $this->incident->tenant);

        $onCallUser = $onCallResolver->getCurrentOnCall($this->incident->tenant_id);

        if (! $onCallUser) {
            Log::warning("No on-call user found for incident #{$this->incident->id} (tenant {$this->incident->tenant_id})");

            return;
        }

        SendNotification::dispatch($this->incident, $onCallUser);
    }
}
