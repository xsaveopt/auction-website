<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('request_started_at', hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $status = $response->getStatusCode();

        // Simple log to stderr (visible in docker logs)
        error_log(sprintf('%s %s %d', $method, $path, $status));

        // Detailed log to file
        /** @var int|null $startedAt */
        $startedAt = $request->attributes->get('request_started_at');
        $durationMs = $startedAt !== null ? round((hrtime(true) - $startedAt) / 1e6, 2) : null;

        Log::channel('access')->info(
            sprintf('%s %s %d', $method, $path, $status),
            array_filter(
                [
                    'ip' => $request->ip(),
                    'method' => $method,
                    'path' => $path,
                    'query' => $request->getQueryString(),
                    'status' => $status,
                    'duration_ms' => $durationMs,
                    'user_id' => $request->user()?->id,
                    'user_agent' => $request->userAgent(),
                ],
                fn($v) => $v !== null && $v !== '',
            ),
        );
    }
}
