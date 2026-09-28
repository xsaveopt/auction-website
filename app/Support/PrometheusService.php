<?php

namespace App\Support;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APCng;

class PrometheusService
{
    private CollectorRegistry $registry;

    private Counter $httpRequestsTotal;

    private Histogram $httpRequestDuration;

    private const EVENTS = [
        'bids_placed' => ['Bids placed or raised', ['source'], [['bidder'], ['admin']]],
        'leftover_items_sold' => ['Leftover items sold', ['channel'], [['buy'], ['admin'], ['price_offer']]],
        'price_offers_submitted' => ['Leftover price offers submitted', [], [[]]],
        'registrations' => ['New user accounts', ['method'], [['password'], ['microsoft']]],
    ];

    /** @var array<string, Counter> */
    private array $eventCounters = [];

    public function __construct()
    {
        $this->registry = new CollectorRegistry(new APCng());

        $this->httpRequestsTotal = $this->registry->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'route', 'status_code'],
        );

        $this->httpRequestDuration = $this->registry->getOrRegisterHistogram(
            'app',
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'route'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
        );

        foreach (self::EVENTS as $event => [$help, $labelNames, $initialLabels]) {
            $counter = $this->registry->getOrRegisterCounter('app', "{$event}_total", $help, $labelNames);
            foreach ($initialLabels as $labels) {
                $counter->incBy(0, $labels);
            }
            $this->eventCounters[$event] = $counter;
        }
    }

    /**
     * @param list<string> $labels
     */
    public function recordEvent(string $event, array $labels = [], int $count = 1): void
    {
        $counter = $this->eventCounters[$event] ?? null;

        if ($counter === null || $count <= 0) {
            return;
        }

        $counter->incBy($count, $labels);
    }

    public function observeRequest(string $method, string $route, int $statusCode, float $durationSeconds): void
    {
        $this->httpRequestsTotal->incBy(1, [$method, $route, (string) $statusCode]);
        $this->httpRequestDuration->observe($durationSeconds, [$method, $route]);
    }

    public function registerGauge(string $name, string $help, float $value): void
    {
        $gauge = $this->registry->getOrRegisterGauge('app', $name, $help);
        $gauge->set($value);
    }

    public function renderMetrics(): string
    {
        $renderer = new RenderTextFormat();

        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}
