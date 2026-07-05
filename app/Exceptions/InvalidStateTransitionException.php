<?php

namespace App\Exceptions;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use RuntimeException;

class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly IncidentStatus $fromStatus,
        public readonly IncidentStatus $toStatus,
        public readonly ?IncidentSeverity $incidentSeverity = null,
    ) {
        parent::__construct($this->buildMessage());
    }

    private function buildMessage(): string
    {
        $prefix = $this->incidentSeverity
            ? "Cannot transition {$this->incidentSeverity->value} incident"
            : 'Cannot transition incident';

        $message = "{$prefix} from [{$this->fromStatus->value}] to [{$this->toStatus->value}]";

        if (
            $this->incidentSeverity?->requiresAcknowledgement()
            && $this->fromStatus === IncidentStatus::Open
            && $this->toStatus === IncidentStatus::Resolved
        ) {
            $message .= ' — P1/P2 must be acknowledged first';
        }

        return $message;
    }
}
