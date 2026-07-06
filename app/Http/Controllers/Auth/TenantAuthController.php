<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:tenants,slug', 'regex:/^[a-z0-9-]+$/'],
            'plan_tier' => ['required', Rule::in(['free', 'pro', 'enterprise'])],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:150'],
        ]);

        [$tenant, $user, $apiKey, $plaintextKey] = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'plan_tier' => $validated['plan_tier'],
                'is_active' => true,
            ]);

            // No ResolveTenant middleware has run for this public endpoint,
            // so bind the newly created tenant ourselves — the User and
            // TenantApiKey created below rely on BelongsToTenant's creating
            // hook to auto-fill tenant_id.
            app()->instance('current_tenant', $tenant);

            $user = $tenant->users()->create([
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'role' => 'owner',
            ]);

            [$apiKey, $plaintextKey] = $this->createApiKey($tenant, 'Default Key');

            return [$tenant, $user, $apiKey, $plaintextKey];
        });

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan_tier' => $tenant->plan_tier,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'api_key' => [
                'key' => $plaintextKey,
                'prefix' => $apiKey->key_prefix,
                'warning' => 'Store this key securely. It will not be shown again.',
            ],
        ], 201);
    }

    public function generateKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        [$apiKey, $plaintextKey] = $this->createApiKey(app('current_tenant'), $validated['name']);

        return response()->json([
            'api_key' => [
                'key' => $plaintextKey,
                'prefix' => $apiKey->key_prefix,
                'warning' => 'Store this key securely. It will not be shown again.',
            ],
        ], 201);
    }

    public function revokeKey(Request $request, $id): JsonResponse
    {
        $apiKey = TenantApiKey::find($id);

        if (! $apiKey) {
            return response()->json(['error' => 'API key not found'], 404);
        }

        if ($apiKey->revoked_at !== null) {
            return response()->json(['error' => 'API key is already revoked'], 409);
        }

        $apiKey->update(['revoked_at' => now()]);

        return response()->json(['message' => 'API key revoked'], 200);
    }

    /**
     * @return array{0: TenantApiKey, 1: string} the created key row and its plaintext value
     */
    private function createApiKey(Tenant $tenant, string $name): array
    {
        $plaintextKey = 'inc_live_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $apiKey = $tenant->apiKeys()->create([
            'key_hash' => hash('sha256', $plaintextKey),
            'key_prefix' => substr($plaintextKey, 0, 8),
            'name' => $name,
        ]);

        return [$apiKey, $plaintextKey];
    }
}
