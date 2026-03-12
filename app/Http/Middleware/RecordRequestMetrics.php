<?php

namespace App\Http\Middleware;

use App\Models\RequestMetric;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordRequestMetrics
{
    private const RECORDING_ATTRIBUTE = 'monitoring.record_request';

    private const START_TIME_ATTRIBUTE = 'monitoring.request_started_at';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shouldRecord = $this->shouldRecord($request);

        $request->attributes->set(self::RECORDING_ATTRIBUTE, $shouldRecord);

        if ($shouldRecord) {
            $request->attributes->set(self::START_TIME_ATTRIBUTE, hrtime(true));
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->get(self::RECORDING_ATTRIBUTE) !== true) {
            return;
        }

        $startedAt = $request->attributes->get(self::START_TIME_ATTRIBUTE);

        if (!is_int($startedAt)) {
            return;
        }

        $routeUri = $request->route()?->uri();
        $path = $routeUri !== null ? '/' . $routeUri : '/' . $request->path();
        $durationMs = max((int) round((hrtime(true) - $startedAt) / 1_000_000), 0);

        RequestMetric::query()->create([
            'method' => $request->method(),
            'path' => $path,
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);

        if (random_int(1, 100) === 1) {
            RequestMetric::pruneOlderThan(now()->subDay());
        }
    }

    private function shouldRecord(Request $request): bool
    {
        return !$request->is('api/admin/monitoring*');
    }
}
