<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function register(Request $request): JsonResponse
    {
        if ($this->ssoEnabled()) {
            return response()->json([
                'message' => 'SSO is enabled. Please sign in with Microsoft.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var array{username: string, password: string} $validated */
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:100', 'unique:users'],
            'password' => ['required', Password::min(6)],
        ]);

        $user = User::create($validated);

        Auth::login($user);

        return response()->json(['user' => $this->userResponse($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        if ($this->ssoEnabled()) {
            return response()->json([
                'message' => 'SSO is enabled. Please sign in with Microsoft.',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var array{username: string, password: string} $credentials */
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($response = $this->loginThrottleResponse($request)) {
            return $response;
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->loginThrottleKey($request), self::LOGIN_DECAY_SECONDS);

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        RateLimiter::clear($this->loginThrottleKey($request));
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return response()->json(['user' => $this->userResponse($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['user' => null]);
        }

        return response()->json(['user' => $this->userResponse($user)]);
    }

    /** @return array{id: int, username: string, is_admin: bool} */
    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'is_admin' => (bool) $user->is_admin,
        ];
    }

    private function ssoEnabled(): bool
    {
        return filled(config('services.microsoft.client_id')) && filled(config('services.microsoft.client_secret'));
    }

    private function loginThrottleResponse(Request $request): ?JsonResponse
    {
        $key = $this->loginThrottleKey($request);

        if (!RateLimiter::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS)) {
            return null;
        }

        $retryAfter = RateLimiter::availableIn($key);

        return response()
            ->json([
                'message' => 'Too many login attempts. Please try again later.',
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS)
            ->header('Retry-After', (string) $retryAfter);
    }

    private function loginThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('username')->toString()) . '|' . ($request->ip() ?? ''));
    }
}
