<?php

namespace Tests\Unit;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Support\AuctionService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AuctionServiceTest extends TestCase
{
    public function test_latest_bids_only_return_the_newest_bid_per_user(): void
    {
        $auction = $this->makeAuction(3, collect([
            $this->makeBid(10, 1, '12.00', 1),
            $this->makeBid(11, 2, '13.00', 1),
            $this->makeBid(12, 1, '15.00', 2),
        ]));

        $latestBids = new AuctionService()->latestBids($auction);

        $this->assertCount(2, $latestBids);
        $this->assertSame([12, 11], $latestBids->pluck('id')->all());
    }

    public function test_allocate_uses_uniform_pricing_when_last_winner_gets_full_quantity(): void
    {
        $auction = $this->makeAuction(3, collect([
            $this->makeBid(21, 1, '20.00', 2),
            $this->makeBid(22, 2, '15.00', 1),
            $this->makeBid(23, 3, '12.00', 1),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([21 => 2, 22 => 1], $result['allocations']);
        $this->assertSame(15.0, $result['clearing_price']);
        $this->assertSame([21 => 15.0, 22 => 15.0], $result['prices']);
    }

    public function test_allocate_uses_pay_your_bid_when_last_winner_is_partially_filled(): void
    {
        $auction = $this->makeAuction(3, collect([
            $this->makeBid(31, 1, '20.00', 2),
            $this->makeBid(32, 2, '15.00', 2),
            $this->makeBid(33, 3, '12.00', 1),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([31 => 2, 32 => 1], $result['allocations']);
        $this->assertSame(15.0, $result['clearing_price']);
        $this->assertSame([31 => 20.0, 32 => 15.0], $result['prices']);
    }

    public function test_allocate_without_bids_returns_starting_price_and_no_allocations(): void
    {
        $auction = $this->makeAuction(3, new Bid()->newCollection());

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([], $result['allocations']);
        $this->assertSame(10.0, $result['clearing_price']);
        $this->assertSame([], $result['prices']);
    }

    public function test_allocate_breaks_equal_amount_ties_by_earliest_bid_when_oversubscribed(): void
    {
        $auction = $this->makeAuction(2, collect([
            $this->makeBid(43, 3, '15.00', 1),
            $this->makeBid(41, 1, '15.00', 1),
            $this->makeBid(42, 2, '15.00', 1),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([41 => 1, 42 => 1], $result['allocations']);
        $this->assertSame(15.0, $result['clearing_price']);
        $this->assertSame([41 => 15.0, 42 => 15.0], $result['prices']);
    }

    public function test_allocate_prefers_the_larger_quantity_when_amounts_tie(): void
    {
        $auction = $this->makeAuction(2, collect([
            $this->makeBid(51, 1, '15.00', 1),
            $this->makeBid(52, 2, '15.00', 2),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([52 => 2], $result['allocations']);
        $this->assertSame([52 => 15.0], $result['prices']);
    }

    public function test_allocate_only_counts_the_latest_bid_after_a_quantity_change(): void
    {
        $auction = $this->makeAuction(2, collect([
            $this->makeBid(61, 1, '15.00', 1),
            $this->makeBid(62, 2, '14.00', 1),
            $this->makeBid(63, 1, '15.00', 2),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([63 => 2], $result['allocations']);
        $this->assertSame(15.0, $result['clearing_price']);
        $this->assertSame([63 => 15.0], $result['prices']);
    }

    public function test_allocate_single_winner_partial_fill_pays_their_own_bid(): void
    {
        $auction = $this->makeAuction(2, collect([
            $this->makeBid(71, 1, '18.00', 5),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([71 => 2], $result['allocations']);
        $this->assertSame(18.0, $result['clearing_price']);
        $this->assertSame([71 => 18.0], $result['prices']);
    }

    public function test_allocate_undersubscribed_auction_uses_the_lowest_winning_bid_as_uniform_price(): void
    {
        $auction = $this->makeAuction(5, collect([
            $this->makeBid(81, 1, '30.00', 1),
            $this->makeBid(82, 2, '12.00', 2),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([81 => 1, 82 => 2], $result['allocations']);
        $this->assertSame(12.0, $result['clearing_price']);
        $this->assertSame([81 => 12.0, 82 => 12.0], $result['prices']);
    }

    public function test_allocate_sorts_amounts_numerically_rather_than_as_strings(): void
    {
        $auction = $this->makeAuction(1, collect([
            $this->makeBid(91, 1, '9.00', 1),
            $this->makeBid(92, 2, '10.00', 1),
        ]));

        $result = new AuctionService()->allocate($auction);

        $this->assertSame([92 => 1], $result['allocations']);
        $this->assertSame(10.0, $result['clearing_price']);
    }

    public function test_allocation_by_user_reports_zero_for_losing_bidders(): void
    {
        $auction = $this->makeAuction(1, collect([
            $this->makeBid(101, 1, '20.00', 1),
            $this->makeBid(102, 2, '15.00', 1),
        ]));

        $this->assertSame([1 => 1, 2 => 0], new AuctionService()->allocationByUser($auction));
    }

    /**
     * @param  Collection<int, Bid>  $bids
     */
    private function makeAuction(int $quantity, Collection $bids): Auction
    {
        $auction = new Auction([
            'title' => 'Test auction',
            'description' => 'Auction description',
            'starting_price' => '10.00',
            'quantity' => $quantity,
            'max_per_bidder' => $quantity,
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);
        $auction->id = 1;
        $auction->setRelation('bids', $bids);
        $auction->setRelation('images', collect());
        $auction->setRelation('leftoverPurchases', collect());

        return $auction;
    }

    private function makeBid(int $id, int $userId, string $amount, int $quantity): Bid
    {
        $user = new User(['username' => "user{$userId}"]);
        $user->id = $userId;

        $bid = new Bid([
            'user_id' => $userId,
            'amount' => $amount,
            'quantity' => $quantity,
        ]);
        $bid->id = $id;
        $bid->setRelation('user', $user);

        return $bid;
    }
}
