<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Http\Requests\UpdateIncidentStatusRequest;
use App\Models\Incident;
use App\Services\IncidentStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(private IncidentStateMachine $stateMachine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $incidents = Incident::query()
            ->select(['id', 'tenant_id', 'title', 'severity', 'status', 'assigned_to', 'created_at', 'acknowledged_at', 'resolved_at', 'closed_at'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($incidents);
    }

    public function show(Incident $incident): JsonResponse
    {
        $incident->load(['stateLogs' => fn ($q) => $q->orderBy('created_at')]);

        return response()->json($incident);
    }

    public function acknowledge(UpdateIncidentStatusRequest $request, Incident $incident): JsonResponse
    {
        return $this->applyTransition($request, $incident, IncidentStatus::Acknowledged);
    }

    public function resolve(UpdateIncidentStatusRequest $request, Incident $incident): JsonResponse
    {
        return $this->applyTransition($request, $incident, IncidentStatus::Resolved);
    }

    public function close(UpdateIncidentStatusRequest $request, Incident $incident): JsonResponse
    {
        return $this->applyTransition($request, $incident, IncidentStatus::Closed);
    }

    public function logs(Request $request, Incident $incident): JsonResponse
    {
        $logs = $incident->stateLogs()
            ->select(['id', 'incident_id', 'from_status', 'to_status', 'changed_by', 'reason', 'metadata', 'created_at'])
            ->orderBy('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($logs);
    }

    private function applyTransition(UpdateIncidentStatusRequest $request, Incident $incident, IncidentStatus $toStatus): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('assigned_to', $validated)) {
            $incident->update(['assigned_to' => $validated['assigned_to']]);
        }

        try {
            $incident = $this->stateMachine->transition(
                $incident,
                $toStatus,
                null,
                $validated['reason'] ?? null,
            );
        } catch (InvalidStateTransitionException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($incident);
    }
}
