<?php

namespace Tests\Unit;

use App\Support\PrometheusService;
use Tests\TestCase;

class PrometheusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('apcu') || !apcu_enabled()) {
            $this->markTestSkipped('The real Prometheus service needs the apcu extension with apc.enable_cli=1.');
        }

        apcu_clear_cache();
    }

    protected function tearDown(): void
    {
        if (extension_loaded('apcu')) {
            apcu_clear_cache();
        }

        parent::tearDown();
    }

    public function test_observed_requests_are_rendered_as_counter_and_histogram(): void
    {
        $service = new PrometheusService();
        $service->observeRequest('GET', '/api/auctions', 200, 0.02);
        $service->observeRequest('GET', '/api/auctions', 200, 0.3);
        $service->observeRequest('POST', '/api/login', 422, 0.004);

        $output = $service->renderMetrics();

        $this->assertStringContainsString('# TYPE app_http_requests_total counter', $output);
        $this->assertStringContainsString(
            'app_http_requests_total{method="GET",route="/api/auctions",status_code="200"} 2',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_requests_total{method="POST",route="/api/login",status_code="422"} 1',
            $output,
        );
        $this->assertStringContainsString('# TYPE app_http_request_duration_seconds histogram', $output);
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket{method="GET",route="/api/auctions",le="0.025"} 1',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket{method="GET",route="/api/auctions",le="0.5"} 2',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_count{method="GET",route="/api/auctions"} 2',
            $output,
        );
        $this->assertStringContainsString(
            'app_http_request_duration_seconds_bucket{method="POST",route="/api/login",le="0.005"} 1',
            $output,
        );
    }

    public function test_gauges_are_registered_and_overwritten(): void
    {
        $service = new PrometheusService();
        $service->registerGauge('active_auctions', 'Active auctions', 3);
        $service->registerGauge('active_auctions', 'Active auctions', 7);

        $output = $service->renderMetrics();

        $this->assertStringContainsString('# HELP app_active_auctions Active auctions', $output);
        $this->assertStringContainsString('# TYPE app_active_auctions gauge', $output);
        $this->assertStringContainsString("app_active_auctions 7\n", $output);
        $this->assertStringNotContainsString("app_active_auctions 3\n", $output);
    }

    public function test_metrics_persist_across_service_instances(): void
    {
        new PrometheusService()->observeRequest('GET', '/up', 200, 0.001);
        new PrometheusService()->observeRequest('GET', '/up', 200, 0.001);

        $this->assertStringContainsString(
            'app_http_requests_total{method="GET",route="/up",status_code="200"} 2',
            new PrometheusService()->renderMetrics(),
        );
    }
}
