<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Statuses this status may transition into, gated by incident severity:
     * P1/P2 incidents must pass through Acknowledged before Resolved.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(IncidentSeverity $severity): array
    {
        return match ($this) {
            self::Open => $severity->requiresAcknowledgement()
                ? [self::Acknowledged]
                : [self::Acknowledged, self::Resolved],
            self::Acknowledged => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target, IncidentSeverity $severity): bool
    {
        return in_array($target, $this->allowedTransitions($severity), true);
    }
}
