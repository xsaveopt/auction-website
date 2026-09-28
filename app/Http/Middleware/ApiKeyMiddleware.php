<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!filled($bearer)) {
            return $next($request);
        }

        $user = User::findByApiKey((string) $bearer);

        if ($user !== null && $user->is_admin) {
            Auth::setUser($user);
            $request->attributes->set('api_key_authenticated', true);
        }

        return $next($request);
    }
}
