<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class AlertDeduplicator
{
    private int $windowSeconds = 300;

    public function generateFingerprint(array $alertData, int $tenantId): string
    {
        return hash('sha256', implode('|', [
            $tenantId,
            $alertData['source'],
            $alertData['title'],
        ]));
    }

    public function isDuplicate(string $fingerprint, int $tenantId): bool
    {
        $key = "dedup:{$tenantId}:{$fingerprint}";

        // Returns a status ("OK") if the key was newly set, null if it already existed.
        $isNew = Redis::set($key, 1, 'EX', $this->windowSeconds, 'NX');

        return $isNew === null;
    }
}
