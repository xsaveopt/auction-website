<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function config(): JsonResponse
    {
        return response()->json([
            'configured' => $this->pushNotificationService->isConfigured(),
            'public_key' => $this->pushNotificationService->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{
         *     subscription: array{
         *         endpoint: string,
         *         contentEncoding?: string|null,
         *         keys: array{p256dh: string, auth: string}
         *     }
         * } $validated
         */
        $validated = $request->validate([
            'subscription.endpoint' => ['required', 'string', 'max:2000'],
            'subscription.contentEncoding' => ['nullable', 'string', Rule::in(['aesgcm', 'aes128gcm'])],
            'subscription.keys.p256dh' => ['required', 'string', 'max:1000'],
            'subscription.keys.auth' => ['required', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $subscription = $validated['subscription'];

        PushSubscription::query()->updateOrCreate(['endpoint' => $subscription['endpoint']], [
            'user_id' => $user->id,
            'public_key' => $subscription['keys']['p256dh'],
            'auth_token' => $subscription['keys']['auth'],
            'content_encoding' => $subscription['contentEncoding'] ?? 'aes128gcm',
        ]);

        return response()->json(['message' => 'Push subscription saved.'], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var array{endpoint: string} $validated */
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        PushSubscription::query()
            ->where('user_id', $user->id)
            ->where('endpoint', $validated['endpoint'])
            ->delete();

        return response()->json([], 204);
    }
}
