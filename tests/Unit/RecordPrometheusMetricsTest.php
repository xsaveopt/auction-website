<?php

namespace Tests\Unit;

use App\Http\Middleware\RecordPrometheusMetrics;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fakes\FakePrometheusService;
use Tests\TestCase;

class RecordPrometheusMetricsTest extends TestCase
{
    public function test_handle_stamps_the_start_time_and_passes_the_request_through(): void
    {
        $prometheus = new FakePrometheusService();
        $middleware = new RecordPrometheusMetrics($prometheus);
        $request = Request::create('/api/auctions', 'GET');
        $response = new Response('ok', 201);

        $result = $middleware->handle($request, fn(Request $r) => $response);

        $this->assertSame($response, $result);
        $this->assertIsInt($request->attributes->get('prometheus.start'));
        $this->assertSame([], $prometheus->requests());
    }

    public function test_terminate_records_the_route_template_status_and_duration(): void
    {
        $prometheus = new FakePrometheusService();
        $middleware = new RecordPrometheusMetrics($prometheus);
        $request = Request::create('/api/auctions/42', 'PUT');
        $route = new Route(['PUT'], 'api/auctions/{auction}', fn() => null);
        $route->bind($request);
        $request->setRouteResolver(fn() => $route);

        $middleware->handle($request, fn(Request $r) => new Response());
        $middleware->terminate($request, new Response('', 422));

        $recorded = $prometheus->requests();
        $this->assertCount(1, $recorded);
        $this->assertSame('PUT', $recorded[0]['method']);
        $this->assertSame('/api/auctions/{auction}', $recorded[0]['route']);
        $this->assertSame(422, $recorded[0]['status_code']);
        $this->assertGreaterThanOrEqual(0.0, $recorded[0]['duration_seconds']);
        $this->assertLessThan(60.0, $recorded[0]['duration_seconds']);
    }

    public function test_terminate_falls_back_to_the_path_without_a_route(): void
    {
        $prometheus = new FakePrometheusService();
        $middleware = new RecordPrometheusMetrics($prometheus);
        $request = Request::create('/missing/page', 'GET');

        $middleware->handle($request, fn(Request $r) => new Response());
        $middleware->terminate($request, new Response('', 404));

        $this->assertSame('/missing/page', $prometheus->requests()[0]['route']);
        $this->assertSame(404, $prometheus->requests()[0]['status_code']);
    }

    public function test_terminate_skips_requests_that_were_never_started(): void
    {
        $prometheus = new FakePrometheusService();
        $middleware = new RecordPrometheusMetrics($prometheus);

        $middleware->terminate(Request::create('/api/user', 'GET'), new Response());

        $this->assertSame([], $prometheus->requests());
    }
}
