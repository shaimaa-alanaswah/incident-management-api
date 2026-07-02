<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationPolicy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'repeat_count',
    ];

    protected function casts(): array
    {
        return [
            'repeat_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(EscalationStep::class)->orderBy('step_order');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
