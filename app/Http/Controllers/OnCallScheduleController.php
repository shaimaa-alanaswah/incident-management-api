<?php

namespace App\Http\Controllers;

use App\Models\OnCallSchedule;
use App\Services\OnCallResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnCallScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = now();

        $schedules = OnCallSchedule::query()
            ->select(['id', 'tenant_id', 'name', 'user_id', 'starts_at', 'ends_at', 'rotation_days'])
            ->orderBy('starts_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        $schedules->getCollection()->transform(function (OnCallSchedule $schedule) use ($now) {
            $schedule->is_on_call = $schedule->starts_at->lte($now) && $schedule->ends_at->gte($now);

            return $schedule;
        });

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', app('current_tenant')?->id),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'rotation_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $schedule = OnCallSchedule::create($validated);

        $overlaps = OnCallSchedule::where('user_id', $schedule->user_id)
            ->where('id', '!=', $schedule->id)
            ->where('starts_at', '<', $schedule->ends_at)
            ->where('ends_at', '>', $schedule->starts_at)
            ->exists();

        $response = $schedule->toArray();

        if ($overlaps) {
            $response['warning'] = 'This shift overlaps with another existing shift for the same user.';
        }

        return response()->json($response, 201);
    }

    public function currentOnCall(OnCallResolver $resolver): JsonResponse
    {
        $user = $resolver->getCurrentOnCall(app('current_tenant')->id);

        if (! $user) {
            return response()->json(['message' => 'Nobody is currently on call'], 404);
        }

        return response()->json($user);
    }

    public function destroy(OnCallSchedule $schedule): JsonResponse
    {
        if ($schedule->starts_at->lte(now())) {
            return response()->json([
                'error' => 'Cannot delete a shift that has already started or ended.',
            ], 409);
        }

        $schedule->delete();

        return response()->json(null, 204);
    }
}
