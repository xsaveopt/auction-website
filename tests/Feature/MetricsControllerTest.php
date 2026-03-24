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

        Presence::heartbeat('metrics-home', 'client-1', 'home', null, $watcher->id);

        $response = $this->withHeader('Authorization', 'Bearer test-metrics-token')->get('/metrics');

        $response->assertOk();
        $response->assertSee('app_active_auctions', false);
        $response->assertSee('app_total_bids', false);
        $response->assertSee('app_online_user_last_seen', false);
        $response->assertSee('app_auction_bid_info', false);
        $response->assertSee('app_user_signup_timestamp', false);
    }
}
