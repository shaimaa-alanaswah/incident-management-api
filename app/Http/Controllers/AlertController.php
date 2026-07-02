<?php

namespace App\Http\Controllers;

use App\Http\Requests\IngestAlertRequest;
use App\Jobs\ProcessIncomingAlert;
use App\Models\Alert;
use App\Services\AlertDeduplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private AlertDeduplicator $deduplicator)
    {
    }

    public function ingest(IngestAlertRequest $request): JsonResponse
    {
        $tenantId = app('current_tenant')->id;

        $alert = Alert::create([
            'source' => $request->source,
            'fingerprint' => $this->deduplicator->generateFingerprint(
                $request->validated(),
                $tenantId
            ),
            'title' => $request->title,
            'body' => $request->body ?? [],
            'severity' => $request->severity,
            'status' => 'new',
            'received_at' => now(),
        ]);

        // Small delay to batch near-simultaneous duplicate alerts before dedup runs.
        ProcessIncomingAlert::dispatch($alert)->delay(now()->addSeconds(2));

        return response()->json([
            'message' => 'Alert received',
            'alert_id' => $alert->id,
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->select(['id', 'tenant_id', 'incident_id', 'source', 'title', 'severity', 'status', 'received_at', 'created_at'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')))
            ->orderByDesc('received_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($alerts);
    }
}
