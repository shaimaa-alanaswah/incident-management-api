<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'incident_id',
        'source',
        'fingerprint',
        'title',
        'body',
        'severity',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'severity' => IncidentSeverity::class,
            'received_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
