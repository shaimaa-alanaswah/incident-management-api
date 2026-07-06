<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = NotificationLog::query()
            ->select(['id', 'tenant_id', 'incident_id', 'user_id', 'channel', 'status', 'sent_at', 'error_message', 'created_at'])
            ->with([
                'incident:id,title',
                'user:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->query('channel')))
            ->when($request->filled('incident_id'), fn ($q) => $q->where('incident_id', $request->query('incident_id')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($logs);
    }
}
