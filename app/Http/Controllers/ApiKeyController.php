<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'api_key' => [
                'exists' => $user->api_key_hash !== null,
                'created_at' => $user->api_key_created_at?->toISOString(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $replaced = $user->api_key_hash !== null;
        $key = $user->generateApiKey();

        AuditLog::record($user, $replaced ? 'api_key.regenerate' : 'api_key.create', $user);

        return response()->json([
            'key' => $key,
            'api_key' => [
                'exists' => true,
                'created_at' => $user->api_key_created_at?->toISOString(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->api_key_hash !== null) {
            $user->revokeApiKey();
            AuditLog::record($user, 'api_key.delete', $user);
        }

        return response()->json([
            'api_key' => [
                'exists' => false,
                'created_at' => null,
            ],
        ]);
    }
}
