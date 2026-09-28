<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Support\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionServiceLeftoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_leftover_sold_quantity_counts_purchases_and_accepted_offers_only(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 10,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 2]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 3, 'status' => 'accepted']);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 4, 'status' => 'pending']);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 5, 'status' => 'rejected']);

        $service = new AuctionService();

        $this->assertSame(6, $service->leftoverSoldQuantity($this->reload($auction)));
        $this->assertSame(
            6,
            $service->leftoverSoldQuantity($this->reload($auction)->load([
                'leftoverPurchases',
                'leftoverPriceOffers',
            ])),
        );
    }

    public function test_leftover_sold_quantity_ignores_soft_deleted_purchases(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 2])->delete();
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);

        $this->assertSame(1, new AuctionService()->leftoverSoldQuantity($this->reload($auction)));
    }

    public function test_close_pending_offers_rejects_pending_offers_when_bids_purchases_and_offers_use_all_stock(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 4,
            'max_per_bidder' => 4,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, null, ['amount' => '12.00', 'quantity' => 2]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);
        $accepted = $this->createLeftoverPriceOffer($auction, null, ['quantity' => 1, 'status' => 'accepted']);
        $pending = $this->createLeftoverPriceOffer($auction, null, ['quantity' => 1, 'status' => 'pending']);

        new AuctionService()->closePendingOffersIfSoldOut($this->reload($auction));

        $this->assertSame('rejected', $this->reload($pending)->status);
        $this->assertSame('accepted', $this->reload($accepted)->status);
    }

    public function test_close_pending_offers_keeps_pending_offers_while_stock_remains(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 4,
            'max_per_bidder' => 4,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, null, ['amount' => '12.00', 'quantity' => 2]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);
        $pending = $this->createLeftoverPriceOffer($auction, null, ['quantity' => 3, 'status' => 'pending']);

        new AuctionService()->closePendingOffersIfSoldOut($this->reload($auction));

        $this->assertSame('pending', $this->reload($pending)->status);
    }

    public function test_allocate_uses_the_updated_bid_and_auction_quantity(): void
    {
        $first = $this->createUser();
        $auction = $this->createAuction(null, [
            'quantity' => 3,
            'max_per_bidder' => 3,
        ]);
        $bid = $this->createBid($auction, $first, ['amount' => '15.00', 'quantity' => 1]);
        $other = $this->createBid($auction, null, ['amount' => '12.00', 'quantity' => 2]);

        $bid->update(['quantity' => 3]);
        $auction->update(['quantity' => 2]);

        $result = new AuctionService()->allocate($this->reload($auction)->load('bids'));

        $this->assertSame([$bid->id => 2], $result['allocations']);
        $this->assertArrayNotHasKey($other->id, $result['allocations']);
        $this->assertSame([$bid->id => 15.0], $result['prices']);
    }

    public function test_auction_response_reports_leftover_quantity_net_of_bids_purchases_and_accepted_offers(): void
    {
        $auction = $this->createAuction(null, [
            'starting_price' => '20.00',
            'quantity' => 10,
            'max_per_bidder' => 10,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, null, ['amount' => '25.00', 'quantity' => 3]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 2]);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 1, 'status' => 'accepted']);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 2, 'status' => 'pending']);

        $response = new AuctionService()->freshAuctionResponse($this->reload($auction));

        $this->assertSame(3, $response['items_allocated']);
        $this->assertSame(4, $response['leftover_quantity']);
        $this->assertSame('15.00', $response['leftover_price']);
    }

    public function test_available_leftover_quantity_subtracts_allocations_purchases_and_accepted_offers(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 10,
            'max_per_bidder' => 10,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, null, ['amount' => '25.00', 'quantity' => 3]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 2]);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 1, 'status' => 'accepted']);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 2, 'status' => 'pending']);

        $service = new AuctionService();
        $fresh = $this->reload($auction);

        $this->assertSame(6, $service->committedQuantity($fresh));
        $this->assertSame(4, $service->availableLeftoverQuantity($fresh));
        $this->assertSame(4, $service->availableLeftoverQuantity($fresh, $service->allocate($fresh)));
    }

    public function test_available_leftover_quantity_never_goes_below_zero(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 2,
            'max_per_bidder' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, null, ['amount' => '25.00', 'quantity' => 2]);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);

        $fresh = $this->reload($auction);

        $this->assertSame(0, new AuctionService()->availableLeftoverQuantity($fresh));
    }

    public function test_leftover_price_applies_the_configured_factor_rounded_to_cents(): void
    {
        $auction = $this->createAuction(null, ['starting_price' => '10.99']);

        $this->assertSame(8.24, new AuctionService()->leftoverPrice($auction));

        $settings = SiteSetting::instance();
        $settings->leftover_price_factor = 0.5;
        $settings->save();

        $this->assertSame(5.5, new AuctionService()->leftoverPrice($auction));
    }
}
