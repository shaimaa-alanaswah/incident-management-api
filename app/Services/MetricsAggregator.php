<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

class MetricsAggregator
{
    /**
     * All queries take the tenant id explicitly and bypass the ambient
     * container-bound tenant scope, so this service is safe to call from
     * any context (HTTP, queue worker, cross-tenant system sweep).
     */
    public function getOverview(int $tenantId): array
    {
        $counts = Incident::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_incidents,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count
            ")
            ->first();

        $mttr = Incident::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('resolved_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_mttr_minutes'))
            ->value('avg_mttr_minutes');

        return [
            'total_incidents' => (int) $counts->total_incidents,
            'open_count' => (int) $counts->open_count,
            'acknowledged_count' => (int) $counts->acknowledged_count,
            'resolved_count' => (int) $counts->resolved_count,
            'closed_count' => (int) $counts->closed_count,
            'avg_mttr_minutes' => $mttr !== null ? round((float) $mttr, 2) : null,
        ];
    }

    public function getVolume(int $tenantId, string $groupBy = 'hour'): array
    {
        $periodExpression = $groupBy === 'day'
            ? 'DATE(received_at)'
            : "DATE_FORMAT(received_at, '%Y-%m-%d %H:00')";

        return Alert::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('received_at', '>=', now()->subDays(7))
            ->select(DB::raw("{$periodExpression} as period"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw($periodExpression))
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => ['period' => (string) $row->period, 'count' => (int) $row->count])
            ->all();
    }
}
