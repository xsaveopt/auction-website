<?php

namespace Tests\Feature;

use App\Support\PrometheusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePrometheusService;
use Tests\TestCase;

class RequestObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_are_recorded_in_the_fake_prometheus_service(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertOk();

        /** @var FakePrometheusService $prometheus */
        $prometheus = $this->app->make(PrometheusService::class);

        $this->assertNotEmpty($prometheus->requests());
        $this->assertSame('GET', $prometheus->requests()[0]['method']);
        $this->assertSame('/api/user', $prometheus->requests()[0]['route']);
        $this->assertSame(200, $prometheus->requests()[0]['status_code']);
    }

    public function test_request_logging_writes_to_access_and_debug_channels(): void
    {
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')->twice();

        Log::shouldReceive('channel')
            ->once()
            ->with('access')
            ->andReturn($logger);
        Log::shouldReceive('channel')
            ->once()
            ->with('debug')
            ->andReturn($logger);

        $this->getJson('/api/user')->assertOk();
    }
}
