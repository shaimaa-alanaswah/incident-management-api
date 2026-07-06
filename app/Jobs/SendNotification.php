<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Models\Incident;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Incident $incident,
        public User $user,
        public NotificationChannel $channel = NotificationChannel::Email,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        app()->instance('current_tenant', $this->incident->tenant);

        $log = NotificationLog::create([
            'incident_id' => $this->incident->id,
            'user_id' => $this->user->id,
            'channel' => $this->channel,
            'payload' => [
                'incident_title' => $this->incident->title,
                'severity' => $this->incident->severity->value,
            ],
            'status' => 'queued',
        ]);

        try {
            Log::info("Simulated notification sent to {$this->user->email} for incident #{$this->incident->id}: {$this->incident->title}");

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
