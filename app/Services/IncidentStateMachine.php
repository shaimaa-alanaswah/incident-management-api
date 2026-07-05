<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Incident;
use App\Models\IncidentStateLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentStateMachine
{
    public function transition(
        Incident $incident,
        IncidentStatus $toStatus,
        ?User $actor = null,
        ?string $reason = null,
    ): Incident {
        $fromStatus = $incident->status;

        if (! $fromStatus->canTransitionTo($toStatus, $incident->severity)) {
            throw new InvalidStateTransitionException($fromStatus, $toStatus, $incident->severity);
        }

        DB::transaction(function () use ($incident, $fromStatus, $toStatus, $actor, $reason) {
            $timestamps = match ($toStatus) {
                IncidentStatus::Acknowledged => ['acknowledged_at' => now()],
                IncidentStatus::Resolved => ['resolved_at' => now()],
                IncidentStatus::Closed => ['closed_at' => now()],
                default => [],
            };

            $incident->update(array_merge(['status' => $toStatus], $timestamps));

            IncidentStateLog::create([
                'incident_id' => $incident->id,
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
                'changed_by' => $actor?->id,
                'reason' => $reason,
            ]);
        });

        return $incident->fresh();
    }
}
