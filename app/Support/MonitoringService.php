<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\RequestMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MonitoringService
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    /** @return array<string, mixed> */
    public function getDashboard(): array
    {
        $stats = $this->statsService->getStats();

        return [
            'generated_at' => now()->toIso8601String(),
            'requests' => $this->requestSummary(),
            'traffic' => $this->trafficSeries(),
            'slow_paths' => $this->slowPaths(),
            'status_codes' => $this->statusCodeBreakdown(),
            'application' => [
                'online_users' => (int) $stats['online_users'],
                'active_auctions' => (int) $stats['active_auctions'],
                'total_bids' => (int) $stats['total_bids'],
                'bids_today' => $this->bidsToday($stats),
                'current_bid_total' => (float) $stats['current_bid_total'],
                'top_watched_auctions' => $this->topWatchedAuctions(),
                'hot_auctions' => $stats['hot_auctions']->values()->all(),
            ],
            'caddy' => $this->caddySummary(),
        ];
    }

    /** @param array<string, mixed> $stats */
    private function bidsToday(array $stats): int
    {
        /** @var list<array{label: string, date: string, count: int}> $bidsPerDay */
        $bidsPerDay = $stats['bids_per_day'];

        if ($bidsPerDay === []) {
            return 0;
        }

        return (int) $bidsPerDay[array_key_last($bidsPerDay)]['count'];
    }

    /** @return array<string, float|int|null> */
    private function requestSummary(): array
    {
        $lastMinute = $this->baseMetricsQuery(now()->subMinute());
        $lastFiveMinutes = $this->baseMetricsQuery(now()->subMinutes(5));
        $lastHour = $this->baseMetricsQuery(now()->subHour());

        $requestsLastMinute = (clone $lastMinute)->count();
        $requestsLastFiveMinutes = (clone $lastFiveMinutes)->count();
        $serverErrorsLastFiveMinutes = (clone $lastFiveMinutes)->where('status', '>=', 500)->count();
        $slowRequestsLastFiveMinutes = (clone $lastFiveMinutes)->where('duration_ms', '>=', 1000)->count();
        $avgLatencyLastFiveMinutes = $this->nullableFloat((clone $lastFiveMinutes)->avg('duration_ms'));
        $maxLatencyLastHour = $this->nullableInt((clone $lastHour)->max('duration_ms'));

        return [
            'requests_last_minute' => $requestsLastMinute,
            'requests_per_second' => round($requestsLastMinute / 60, 2),
            'requests_last_five_minutes' => $requestsLastFiveMinutes,
            'average_latency_ms' => round($avgLatencyLastFiveMinutes ?? 0.0, 1),
            'p50_latency_ms' => $this->percentile(clone $lastFiveMinutes, 0.50),
            'p95_latency_ms' => $this->percentile(clone $lastFiveMinutes, 0.95),
            'max_latency_ms' => $maxLatencyLastHour,
            'server_errors_last_five_minutes' => $serverErrorsLastFiveMinutes,
            'error_rate_percent' => $requestsLastFiveMinutes > 0
                ? round(($serverErrorsLastFiveMinutes / $requestsLastFiveMinutes) * 100, 1)
                : 0.0,
            'slow_requests_last_five_minutes' => $slowRequestsLastFiveMinutes,
        ];
    }

    /** @return list<array{minute: string, request_count: int, avg_latency_ms: float, error_count: int}> */
    private function trafficSeries(): array
    {
        $start = now()->subMinutes(29)->startOfMinute();
        $end = now()->startOfMinute();

        $rows = DB::table('request_metrics')
            ->selectRaw("strftime('%Y-%m-%dT%H:%M:00Z', created_at) as minute")
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('AVG(duration_ms) as avg_latency_ms')
            ->selectRaw('SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END) as error_count')
            ->where('created_at', '>=', $start)
            ->groupBy('minute')
            ->orderBy('minute')
            ->get();

        /** @var array<string, array{minute: string, request_count: int, avg_latency_ms: float, error_count: int}> $indexedRows */
        $indexedRows = [];

        foreach ($rows as $row) {
            /** @var object{minute: string, request_count: int|string, avg_latency_ms: float|string|null, error_count: int|string} $row */
            $minute = $row->minute;

            $indexedRows[$minute] = [
                'minute' => Carbon::parse($minute)->toIso8601String(),
                'request_count' => (int) $row->request_count,
                'avg_latency_ms' => round((float) ($row->avg_latency_ms ?? 0), 1),
                'error_count' => (int) $row->error_count,
            ];
        }

        $series = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d\TH:i:00\Z');

            $series[] = $indexedRows[$key] ?? [
                'minute' => $cursor->toIso8601String(),
                'request_count' => 0,
                'avg_latency_ms' => 0.0,
                'error_count' => 0,
            ];

            $cursor->addMinute();
        }

        return $series;
    }

    /** @return list<array{path: string, request_count: int, avg_latency_ms: float, max_latency_ms: int, error_count: int}> */
    private function slowPaths(): array
    {
        $rows = DB::table('request_metrics')
            ->select('path')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('AVG(duration_ms) as avg_latency_ms')
            ->selectRaw('MAX(duration_ms) as max_latency_ms')
            ->selectRaw('SUM(CASE WHEN status >= 500 THEN 1 ELSE 0 END) as error_count')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->groupBy('path')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByDesc('avg_latency_ms')
            ->limit(5)
            ->get();

        /** @var list<array{path: string, request_count: int, avg_latency_ms: float, max_latency_ms: int, error_count: int}> $paths */
        $paths = [];

        foreach ($rows as $row) {
            /** @var object{path: string, request_count: int|string, avg_latency_ms: float|string|null, max_latency_ms: int|string|null, error_count: int|string} $row */
            $paths[] = [
                'path' => $row->path,
                'request_count' => (int) $row->request_count,
                'avg_latency_ms' => round((float) ($row->avg_latency_ms ?? 0), 1),
                'max_latency_ms' => (int) ($row->max_latency_ms ?? 0),
                'error_count' => (int) $row->error_count,
            ];
        }

        return $paths;
    }

    /** @return list<array{status: int, request_count: int}> */
    private function statusCodeBreakdown(): array
    {
        $rows = DB::table('request_metrics')
            ->select('status')
            ->selectRaw('COUNT(*) as request_count')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        /** @var list<array{status: int, request_count: int}> $statuses */
        $statuses = [];

        foreach ($rows as $row) {
            /** @var object{status: int|string, request_count: int|string} $row */
            $statuses[] = [
                'status' => (int) $row->status,
                'request_count' => (int) $row->request_count,
            ];
        }

        return $statuses;
    }

    /** @return list<array{id: int, title: string, watcher_count: int}> */
    private function topWatchedAuctions(): array
    {
        $auctions = Auction::query()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->select('id', 'title')
            ->selectSub(Presence::watcherCountSubquery(), 'watcher_count')
            ->orderByDesc('watcher_count')
            ->limit(5)
            ->get();

        /** @var list<array{id: int, title: string, watcher_count: int}> $items */
        $items = [];

        foreach ($auctions as $auction) {
            if ((int) $auction->watcher_count <= 0) {
                continue;
            }

            $items[] = [
                'id' => $auction->id,
                'title' => $auction->title,
                'watcher_count' => (int) $auction->watcher_count,
            ];
        }

        return $items;
    }

    /** @return array<string, float|int|bool|string|null> */
    private function caddySummary(): array
    {
        $metricsUrl = config('services.caddy.metrics_url');
        $metricsUrl = is_string($metricsUrl) && $metricsUrl !== '' ? $metricsUrl : 'http://127.0.0.1:2019/metrics';

        try {
            $metrics = $this->parsePrometheusMetrics(
                Http::timeout(2)
                    ->accept('text/plain')
                    ->get($metricsUrl)
                    ->throw()
                    ->body(),
            );
        } catch (ConnectionException|RequestException $exception) {
            return [
                'available' => false,
                'metrics_url' => $metricsUrl,
                'error' => $exception->getMessage(),
                'goroutines' => null,
                'heap_inuse_bytes' => null,
                'resident_memory_bytes' => null,
                'total_http_requests' => null,
                'average_response_ms' => null,
                'uptime_seconds' => null,
            ];
        }

        $requestCount = $metrics['caddy_http_request_duration_seconds_count'] ?? 0.0;
        $requestDurationSum = $metrics['caddy_http_request_duration_seconds_sum'] ?? 0.0;
        $processStartTime = $metrics['process_start_time_seconds'] ?? null;

        return [
            'available' => true,
            'metrics_url' => $metricsUrl,
            'error' => null,
            'goroutines' => isset($metrics['go_goroutines']) ? (int) round($metrics['go_goroutines']) : null,
            'heap_inuse_bytes' => isset($metrics['go_memstats_heap_inuse_bytes'])
                ? (int) round($metrics['go_memstats_heap_inuse_bytes'])
                : null,
            'resident_memory_bytes' => isset($metrics['process_resident_memory_bytes'])
                ? (int) round($metrics['process_resident_memory_bytes'])
                : null,
            'total_http_requests' => (int) round($requestCount),
            'average_response_ms' => $requestCount > 0 ? round(($requestDurationSum / $requestCount) * 1000, 1) : null,
            'uptime_seconds' => $processStartTime !== null
                ? max((int) round(now()->getTimestamp() - (float) $processStartTime), 0)
                : null,
        ];
    }

    /** @return array<string, float> */
    private function parsePrometheusMetrics(string $body): array
    {
        $metrics = [];

        foreach (preg_split("/(\r\n|\n|\r)/", $body) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!preg_match('/^(?<name>[^{\s]+)(?:\{[^}]*\})?\s+(?<value>\S+)$/', $trimmed, $matches)) {
                continue;
            }

            $value = $this->parseMetricValue($matches['value']);

            if ($value === null) {
                continue;
            }

            $name = $matches['name'];
            $metrics[$name] = ($metrics[$name] ?? 0.0) + $value;
        }

        return $metrics;
    }

    private function parseMetricValue(string $value): ?float
    {
        return match ($value) {
            '+Inf', '-Inf', 'Inf', 'NaN' => null,
            default => is_numeric($value) ? (float) $value : null,
        };
    }

    /** @param Builder<RequestMetric> $query */
    private function percentile(Builder $query, float $percentile): ?int
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        $offset = max((int) ceil($total * $percentile) - 1, 0);
        /** @var int|float|string|null $value */
        $value = (clone $query)->orderBy('duration_ms')->offset($offset)->value('duration_ms');

        return $this->nullableInt($value);
    }

    /** @return Builder<RequestMetric> */
    private function baseMetricsQuery(Carbon $since): Builder
    {
        return RequestMetric::query()->recent($since);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
