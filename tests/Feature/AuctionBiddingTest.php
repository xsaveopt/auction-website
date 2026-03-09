<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuctionBiddingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure bidding is open during tests
        Config::set('auction.bidding_closed_start', '09:00');
        Config::set('auction.bidding_closed_end', '09:00'); 
        
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    }

    public function test_user_can_see_their_bids_on_dashboard(): void
    {
        $seller = User::create(['username' => 'seller', 'password' => 'pass']);
        $user = User::create(['username' => 'bidder', 'password' => 'pass']);

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Active Auction',
            'description' => 'Desc',
            'starting_price' => '10.00',
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $wonAuction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Won Auction',
            'description' => 'Desc',
            'starting_price' => '10.00',
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => now()->subDay(),
            'status' => 'active',
        ]);

        // Place bids
        $auction->bids()->create(['user_id' => $user->id, 'amount' => '15.00', 'quantity' => 1]);
        $wonAuction->bids()->create(['user_id' => $user->id, 'amount' => '20.00', 'quantity' => 1]);

        $response = $this->actingAs($user)->getJson('/api/my-auctions');

        $response->assertOk()
            ->assertJsonCount(1, 'active')
            ->assertJsonCount(1, 'won')
            ->assertJsonPath('active.0.title', 'Active Auction')
            ->assertJsonPath('won.0.title', 'Won Auction');
    }

    public function test_soft_close_extends_auction_time(): void
    {
        $seller = User::create(['username' => 'seller', 'password' => 'pass']);
        $user = User::create(['username' => 'bidder', 'password' => 'pass']);

        // Auction ending in 1 minute
        $endTime = now()->addMinute();
        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Sniping Target',
            'description' => 'Desc',
            'starting_price' => '10.00',
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => $endTime,
            'status' => 'active',
        ]);

        $this->actingAs($user)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15.00,
            'quantity' => 1,
        ])->assertCreated();

        $auction->refresh();
        
        $this->assertTrue($auction->ends_at->gt($endTime));
        $this->assertEquals($endTime->addMinutes(2)->timestamp, $auction->ends_at->timestamp);
    }

    public function test_soft_close_does_not_extend_if_bid_is_early(): void
    {
        $seller = User::create(['username' => 'seller', 'password' => 'pass']);
        $user = User::create(['username' => 'bidder', 'password' => 'pass']);

        // Auction ending in 10 minutes
        $endTime = now()->addMinutes(10);
        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Early Bid',
            'description' => 'Desc',
            'starting_price' => '10.00',
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => $endTime,
            'status' => 'active',
        ]);

        $this->actingAs($user)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15.00,
            'quantity' => 1,
        ])->assertCreated();

        $auction->refresh();
        
        $this->assertEquals($endTime->timestamp, $auction->ends_at->timestamp, "Auction end time was extended when it should not have been.");
    }
}
