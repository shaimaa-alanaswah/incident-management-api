<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationStep extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'escalation_policy_id',
        'step_order',
        'delay_minutes',
        'notify_user_id',
        'notify_channel',
    ];

    protected function casts(): array
    {
        return [
            'notify_channel' => NotificationChannel::class,
            'step_order' => 'integer',
            'delay_minutes' => 'integer',
        ];
    }

    public function escalationPolicy(): BelongsTo
    {
        return $this->belongsTo(EscalationPolicy::class);
    }

    public function notifyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notify_user_id');
    }
}
