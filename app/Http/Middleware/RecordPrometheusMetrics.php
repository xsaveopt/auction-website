<?php

namespace App\Http\Middleware;

use App\Support\PrometheusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPrometheusMetrics
{
    public function __construct(
        private readonly PrometheusService $prometheus,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('prometheus.start', hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get('prometheus.start');

        if (!is_int($startedAt)) {
            return;
        }

        $routeUri = $request->route()?->uri();
        $route = $routeUri !== null ? '/' . $routeUri : '/' . $request->path();
        $durationSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

        $this->prometheus->observeRequest($request->method(), $route, $response->getStatusCode(), $durationSeconds);
    }
}
