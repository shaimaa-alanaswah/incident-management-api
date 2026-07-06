<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateEscalationPolicyRequest;
use App\Models\EscalationPolicy;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EscalationPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $policies = EscalationPolicy::query()
            ->select(['id', 'tenant_id', 'name', 'repeat_count', 'created_at'])
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($policies);
    }

    public function store(CreateEscalationPolicyRequest $request): JsonResponse
    {
        $policy = DB::transaction(function () use ($request) {
            $policy = EscalationPolicy::create([
                'name' => $request->validated('name'),
                'repeat_count' => $request->validated('repeat_count') ?? 0,
            ]);

            foreach ($request->validated('steps') as $step) {
                $policy->steps()->create($step);
            }

            return $policy;
        });

        return response()->json($policy->load('steps'), 201);
    }

    public function show(EscalationPolicy $policy): JsonResponse
    {
        $policy->load('steps');

        return response()->json($policy);
    }

    public function update(CreateEscalationPolicyRequest $request, EscalationPolicy $policy): JsonResponse
    {
        DB::transaction(function () use ($request, $policy) {
            $policy->update([
                'name' => $request->validated('name'),
                'repeat_count' => $request->validated('repeat_count') ?? 0,
            ]);

            // Replace all steps rather than diffing — simpler and the whole
            // set is always small.
            $policy->steps()->delete();

            foreach ($request->validated('steps') as $step) {
                $policy->steps()->create($step);
            }
        });

        return response()->json($policy->fresh('steps'));
    }

    public function destroy(EscalationPolicy $policy): JsonResponse
    {
        $hasActiveIncidents = Incident::where('escalation_policy_id', $policy->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->exists();

        if ($hasActiveIncidents) {
            return response()->json([
                'error' => 'Cannot delete escalation policy: it is referenced by one or more active incidents.',
            ], 409);
        }

        $policy->delete();

        return response()->json(null, 204);
    }
}
