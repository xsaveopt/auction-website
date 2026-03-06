<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\PresenceHeartbeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_and_auction_details_report_distinct_live_presence(): void
    {
        $auction = $this->createAuction('Rare Console');

        $this->heartbeat('page-a', 'client-a', 'auction', $auction->id);
        $this->heartbeat('page-b', 'client-a', 'auction', $auction->id);
        $this->heartbeat('page-c', 'client-b', 'home');

        PresenceHeartbeat::query()->create([
            'page_id' => 'stale-page',
            'client_id' => 'client-stale',
            'page_type' => 'auction',
            'auction_id' => $auction->id,
            'last_seen_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('stats.online_users', 2);

        $this->getJson("/api/auctions/{$auction->id}")
            ->assertOk()
            ->assertJsonPath('auction.watcher_count', 1);
    }

    public function test_auction_index_orders_live_listings_by_watcher_count(): void
    {
        $mostWatched = $this->createAuction('Vintage Camera');
        $lessWatched = $this->createAuction('Desk Lamp');

        $this->heartbeat('page-1', 'client-a', 'auction', $mostWatched->id);
        $this->heartbeat('page-2', 'client-b', 'auction', $mostWatched->id);
        $this->heartbeat('page-3', 'client-c', 'auction', $lessWatched->id);

        $this->getJson('/api/auctions')
            ->assertOk()
            ->assertJsonPath('auctions.0.id', $mostWatched->id)
            ->assertJsonPath('auctions.0.watcher_count', 2)
            ->assertJsonPath('auctions.1.id', $lessWatched->id)
            ->assertJsonPath('auctions.1.watcher_count', 1);
    }

    private function createAuction(string $title): Auction
    {
        $seller = User::query()->create([
            'username' => strtolower(str_replace(' ', '-', $title)).'@example.com',
            'password' => 'password123',
        ]);

        return $seller->auctions()->create([
            'title' => $title,
            'description' => 'A collectible item.',
            'starting_price' => 10,
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);
    }

    private function heartbeat(string $pageId, string $clientId, string $pageType, ?int $auctionId = null): void
    {
        $payload = [
            'page_id' => $pageId,
            'client_id' => $clientId,
            'page_type' => $pageType,
        ];

        if ($auctionId !== null) {
            $payload['auction_id'] = $auctionId;
        }

        $this->postJson('/api/presence/heartbeat', $payload)
            ->assertOk();
    }
}
