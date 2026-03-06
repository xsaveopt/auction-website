<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'unique:users', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', Password::min(6)],
        ]);

        $user = User::create($validated);

        Auth::login($user);

        return response()->json(['user' => $this->userResponse($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

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

        if (! $user) {
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
}
