<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case P1 = 'P1';
    case P2 = 'P2';
    case P3 = 'P3';
    case P4 = 'P4';

    /**
     * P1/P2 must go through acknowledgement before they can be resolved;
     * P3/P4 may resolve directly from open.
     */
    public function requiresAcknowledgement(): bool
    {
        return match ($this) {
            self::P1, self::P2 => true,
            self::P3, self::P4 => false,
        };
    }
}
