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
