<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MicrosoftSso;
use App\Support\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialiteController extends Controller
{
    public function enabled(): JsonResponse
    {
        return response()->json(['enabled' => MicrosoftSso::enabled()]);
    }

    public function redirect(): Response
    {
        if (!MicrosoftSso::enabled()) {
            return response()->json(['message' => 'SSO is disabled.'], 403);
        }

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(): RedirectResponse|JsonResponse
    {
        if (!MicrosoftSso::enabled()) {
            return response()->json(['message' => 'SSO is disabled.'], 403);
        }

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (\Exception $e) {
            return redirect('/login?error=sso_failed');
        }

        $microsoftId = strval($microsoftUser->getId());
        $username = $microsoftUser->getEmail() ?: $microsoftId;

        if ($microsoftId === '' || !$username) {
            return redirect('/login?error=sso_profile_invalid');
        }

        $user = User::query()->where('microsoft_id', $microsoftId)->orWhere('username', $username)->first();

        if ($user) {
            $user->username = $username;
            $user->microsoft_id = $microsoftId;
            $user->password = null;
            $user->save();
        } else {
            $user = User::create([
                'username' => $username,
                'microsoft_id' => $microsoftId,
                'password' => null,
            ]);

            app(PrometheusService::class)->recordEvent('registrations', ['microsoft']);
        }

        Auth::login($user);

        return redirect('/');
    }
}
