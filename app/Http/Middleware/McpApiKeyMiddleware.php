<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class McpApiKeyMiddleware
{
    /**
     * Authenticate requests bearing a valid MCP API key as the first admin user.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('auction.mcp_api_key');

        if (!filled($configuredKey)) {
            return $next($request);
        }

        $bearer = $request->bearerToken();

        if (!$bearer || !is_string($configuredKey) || !hash_equals($configuredKey, $bearer)) {
            return $next($request);
        }

        $admin = User::where('is_admin', true)->orderBy('id')->first();

        if ($admin) {
            Auth::setUser($admin);
            $request->attributes->set('mcp_authenticated', true);
        }

        return $next($request);
    }
}
