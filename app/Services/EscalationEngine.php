<?php

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Jobs\SendNotification;
use App\Models\Incident;
use Illuminate\Support\Facades\Log;

class EscalationEngine
{
    /**
     * Determines which escalation step should be firing right now for this
     * incident, based on elapsed time since it opened and the policy's step
     * delays, then dispatches a notification for that step.
     *
     * Each step gets an "active window" exactly as long as its own
     * delay_minutes, chained after the previous step's window. The policy
     * repeats up to repeat_count additional times before being exhausted.
     */
    public function evaluate(Incident $incident): void
    {
        if ($incident->status !== IncidentStatus::Open) {
            return;
        }

        $policy = $incident->escalationPolicy;

        if (! $policy) {
            return;
        }

        $steps = $policy->steps;

        if ($steps->isEmpty()) {
            return;
        }

        $minutesOpen = $incident->created_at->diffInMinutes(now());
        $maxLaps = $policy->repeat_count + 1;

        $thresholds = [];
        $cumulative = 0;

        for ($lap = 0; $lap < $maxLaps; $lap++) {
            foreach ($steps as $step) {
                $cumulative += $step->delay_minutes;
                $thresholds[] = [$cumulative, $step];
            }
        }

        [$lastThreshold, $lastStep] = end($thresholds);
        $exhaustedAt = $lastThreshold + $lastStep->delay_minutes;

        if ($minutesOpen >= $exhaustedAt) {
            Log::warning("Escalation exhausted for incident {$incident->id}");

            return;
        }

        $currentStep = null;

        foreach ($thresholds as [$threshold, $step]) {
            if ($minutesOpen >= $threshold) {
                $currentStep = $step;
            } else {
                break;
            }
        }

        if (! $currentStep || ! $currentStep->notify_user_id) {
            return;
        }

        SendNotification::dispatch($incident, $currentStep->notifyUser, $currentStep->notify_channel);
    }
}
