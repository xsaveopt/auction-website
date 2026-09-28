<?php

namespace Tests\Feature;

use App\Support\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_route_requires_the_configured_token(): void
    {
        $this->get('/metrics')->assertNotFound();
    }

    public function test_metrics_route_renders_application_metrics_when_authorized(): void
    {
        $seller = $this->createUser();
        $watcher = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 2,
            'status' => 'active',
            'ends_at' => now()->addHour(),
        ]);

        $this->createBid($auction, $bidder, [
            'amount' => '18.00',
            'quantity' => 1,
        ]);

        Presence::heartbeat('metrics-home', 'client-1', 'home', '/', null, $watcher->id);

        $response = $this->withHeader('Authorization', 'Bearer test-metrics-token')->get('/metrics');

        $response->assertOk();
        $response->assertSee('app_active_auctions', false);
        $response->assertSee('app_total_bids', false);
        $response->assertSee('app_online_user_last_seen', false);
        $response->assertSee('app_auction_bid_info', false);
        $response->assertSee('app_user_signup_timestamp', false);
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong')->get('/metrics')->assertNotFound();
    }

    public function test_without_a_configured_token_only_the_internal_listener_serves_metrics(): void
    {
        config(['services.metrics.token' => null]);

        $this->get('/metrics')->assertNotFound();
        $this->get('http://localhost:9113/metrics')->assertOk()->assertSee('app_active_auctions', false);
    }

    public function test_metrics_expose_site_info_and_business_event_counters(): void
    {
        $this->app->instance(\App\Support\PrometheusService::class, new \App\Support\PrometheusService());

        $settings = \App\Models\SiteSetting::instance();
        $settings->currency_symbol = '€';
        $settings->save();

        $auction = $this->createAuction($this->createUser(), ['ends_at' => now()->addHour()]);
        $this
            ->actingAs($this->createUser())
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfUnlessApiKey::class)
            ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 12, 'quantity' => 1])
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer test-metrics-token')->get('/metrics')->assertOk();

        $response->assertSee('app_site_info{currency_symbol="€"} 1', false);
        $response->assertSee('app_bids_placed_total{source="bidder"} 1', false);
        $response->assertSee('app_bids_placed_total{source="admin"} 0', false);
        $response->assertSee('app_leftover_items_sold_total{channel="price_offer"} 0', false);
        $response->assertSee(
            'app_http_requests_total{method="POST",route="/api/auctions/{auction}/bids",status_code="201"}',
            false,
        );
    }
}
