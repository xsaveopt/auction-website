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
