<?php

namespace App\Http\Controllers;

use App\Services\MetricsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatsController extends Controller
{
    public function __construct(private MetricsAggregator $metrics)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(
            $this->metrics->getOverview(app('current_tenant')->id)
        );
    }

    public function volume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groupBy' => ['sometimes', Rule::in(['hour', 'day'])],
        ]);

        return response()->json(
            $this->metrics->getVolume(
                app('current_tenant')->id,
                $validated['groupBy'] ?? 'hour'
            )
        );
    }
}
