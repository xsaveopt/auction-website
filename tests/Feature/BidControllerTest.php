<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BidControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_a_bid_on_an_active_auction(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'starting_price' => '10.00',
            'quantity' => 2,
            'max_per_bidder' => 2,
        ]);

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 12,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('bid.amount', '12.00')
            ->assertJsonPath('bid.quantity', 2);

        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $bidder->id,
            'amount' => '12.00',
            'quantity' => 2,
        ]);
    }

    public function test_updating_an_existing_bid_reuses_the_same_database_row(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 2,
            'max_per_bidder' => 2,
        ]);
        $bid = $this->createBid($auction, $bidder, [
            'amount' => '15.00',
            'quantity' => 1,
        ]);

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 18,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('bid.id', $bid->id)
            ->assertJsonPath('bid.amount', '18.00')
            ->assertJsonPath('bid.quantity', 2);

        $this->assertSame(1, Bid::query()->count());
        $this->assertDatabaseHas('bids', [
            'id' => $bid->id,
            'amount' => '18.00',
            'quantity' => 2,
        ]);
    }

    public function test_single_item_bid_updates_force_the_quantity_back_to_one(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'max_per_bidder' => 1,
        ]);
        $bid = $this->createBid($auction, $bidder, [
            'amount' => '14.00',
            'quantity' => 1,
        ]);

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 16,
                'quantity' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('bid.id', $bid->id)
            ->assertJsonPath('bid.quantity', 1);

        $this->assertDatabaseHas('bids', [
            'id' => $bid->id,
            'amount' => '16.00',
            'quantity' => 1,
        ]);
    }

    public function test_seller_cannot_bid_on_their_own_auction(): void
    {
        $seller = $this->createUser();
        $auction = $this->createAuction($seller);

        $this
            ->actingAs($seller)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 12,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot bid on your own auction.');
    }

    public function test_bidding_is_blocked_when_the_site_is_locked(): void
    {
        $auction = $this->createAuction($this->createUser());
        SiteSetting::instance()->update([
            'is_locked' => true,
            'lock_message' => 'Maintenance',
        ]);

        $this
            ->actingAs($this->createUser())
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 12,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The site is temporarily closed for maintenance.');
    }

    public function test_bidding_is_blocked_when_the_schedule_is_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 10:00:00'));

        try {
            config([
                'auction.bidding_schedule_enabled' => true,
                'auction.bidding_closed_start' => '09:00',
                'auction.bidding_closed_end' => '18:00',
                'auction.weekends_open' => false,
            ]);

            $auction = $this->createAuction($this->createUser(), [
                'ends_at' => now()->addHour(),
            ]);

            $this
                ->actingAs($this->createUser())
                ->postJson("/api/auctions/{$auction->id}/bids", [
                    'amount' => 12,
                    'quantity' => 1,
                ])
                ->assertUnprocessable()
                ->assertSee('Bidding is closed during office hours', false);
        } finally {
            Carbon::setTestNow();
            config(['auction.bidding_schedule_enabled' => false]);
        }
    }

    public function test_bidding_is_blocked_for_inactive_auctions(): void
    {
        $auction = $this->createAuction($this->createUser(), [
            'status' => 'ended',
            'ends_at' => now()->subMinute(),
        ]);

        $this
            ->actingAs($this->createUser())
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 12,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This auction is no longer active.');
    }

    public function test_existing_bids_cannot_be_lowered_or_resubmitted_without_a_change(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 2,
            'max_per_bidder' => 2,
        ]);
        $this->createBid($auction, $bidder, [
            'amount' => '15.00',
            'quantity' => 2,
        ]);

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 14,
                'quantity' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot lower your bid amount.');

        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'New bid must have a higher amount or a higher quantity than your current bid.',
            );
    }

    public function test_anti_sniping_extends_the_auction_when_enabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));

        try {
            config([
                'auction.anti_sniping_enabled' => true,
                'auction.anti_sniping_window' => 60,
                'auction.anti_sniping_extension' => 300,
            ]);

            $seller = $this->createUser();
            $auction = $this->createAuction($seller, [
                'ends_at' => now()->addSeconds(30),
            ]);

            $this
                ->actingAs($this->createUser())
                ->postJson("/api/auctions/{$auction->id}/bids", [
                    'amount' => 12,
                    'quantity' => 1,
                ])
                ->assertCreated();

            $this->assertTrue($auction->fresh()->ends_at->equalTo(now()->addSeconds(330)));
        } finally {
            Carbon::setTestNow();
            config(['auction.anti_sniping_enabled' => false]);
        }
    }
}
