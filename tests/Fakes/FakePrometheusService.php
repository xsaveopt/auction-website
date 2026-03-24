<?php

namespace Tests\Fakes;

use App\Support\PrometheusService;

class FakePrometheusService extends PrometheusService
{
    /** @var list<array{method: string, route: string, status_code: int, duration_seconds: float}> */
    private array $requests = [];

    /** @var array<string, array{help: string, value: float}> */
    private array $gauges = [];

    public function __construct() {}

    public function observeRequest(string $method, string $route, int $statusCode, float $durationSeconds): void
    {
        $this->requests[] = [
            'method' => $method,
            'route' => $route,
            'status_code' => $statusCode,
            'duration_seconds' => $durationSeconds,
        ];
    }

    public function registerGauge(string $name, string $help, float $value): void
    {
        $this->gauges[$name] = [
            'help' => $help,
            'value' => $value,
        ];
    }

    public function renderMetrics(): string
    {
        $output = '';

        foreach ($this->gauges as $name => $gauge) {
            $metric = "app_{$name}";
            $output .= "# HELP {$metric} {$gauge['help']}\n";
            $output .= "# TYPE {$metric} gauge\n";
            $output .= "{$metric} {$gauge['value']}\n";
        }

        return $output;
    }

    /**
     * @return list<array{method: string, route: string, status_code: int, duration_seconds: float}>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * @return array<string, array{help: string, value: float}>
     */
    public function gauges(): array
    {
        return $this->gauges;
    }
}
