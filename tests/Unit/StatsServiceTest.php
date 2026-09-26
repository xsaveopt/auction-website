<?php

namespace Tests\Unit;

use App\Support\Presence;
use App\Support\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_stats_are_empty_for_a_fresh_install(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 12:00:00'));

        $stats = new StatsService()->getStats();

        $this->assertSame(0, $stats['active_auctions']);
        $this->assertSame(0, $stats['ended_auctions']);
        $this->assertSame(0, $stats['total_items']);
        $this->assertSame(0, $stats['total_bids']);
        $this->assertSame(0, $stats['total_users']);
        $this->assertSame(0.0, $stats['current_bid_total']);
        $this->assertSame(0, $stats['online_users']);
        $this->assertCount(7, $stats['bids_per_day']);
        $this->assertSame('2026-03-19', $stats['bids_per_day'][0]['date']);
        $this->assertSame('2026-03-25', $stats['bids_per_day'][6]['date']);
        $this->assertSame('Wed', $stats['bids_per_day'][6]['label']);
        $this->assertTrue($stats['hot_auctions']->isEmpty());
        $this->assertTrue($stats['top_bidders']->isEmpty());
    }

    public function test_stats_aggregate_auctions_bids_users_and_presence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-25 12:00:00'));

        $seller = $this->createUser();
        $alice = $this->createUser(['username' => 'alice']);
        $bob = $this->createUser(['username' => 'bob']);

        $hot = $this->createAuction($seller, ['title' => 'Hot', 'quantity' => 3, 'ends_at' => now()->addDay()]);
        $warm = $this->createAuction($seller, ['title' => 'Warm', 'quantity' => 2, 'ends_at' => now()->addDay()]);
        $ended = $this->createAuction($seller, ['status' => 'ended', 'quantity' => 4, 'ends_at' => now()->subDay()]);
        $expired = $this->createAuction($seller, [
            'status' => 'active',
            'quantity' => 1,
            'ends_at' => now()->subHour(),
        ]);
        $this->createAuction($seller, ['status' => 'cancelled', 'ends_at' => now()->addDay()]);

        $this->createBid($hot, $alice, ['amount' => '10.00', 'quantity' => 2]);
        $this->createBid($hot, $bob, ['amount' => '12.50', 'quantity' => 1]);
        $this->createBid($warm, $alice, ['amount' => '5.00', 'quantity' => 1]);
        $old = $this->createBid($ended, $alice, ['amount' => '99.00', 'quantity' => 1]);
        $old->created_at = now()->subDays(3);
        $old->save();
        $this->createBid($expired, $bob, ['amount' => '50.00', 'quantity' => 1]);

        Presence::heartbeat('stats-home', 'client-a', 'home', '/', null, $alice->id);
        Presence::heartbeat('stats-other', 'client-b', 'home', '/', null, $bob->id);

        $stats = new StatsService()->getStats();

        $this->assertSame(2, $stats['active_auctions']);
        $this->assertSame(3, $stats['ended_auctions']);
        $this->assertSame(5, $stats['total_items']);
        $this->assertSame(5, $stats['total_bids']);
        $this->assertSame(3, $stats['total_users']);
        $this->assertSame(37.5, $stats['current_bid_total']);
        $this->assertSame(2, $stats['online_users']);

        $perDay = collect($stats['bids_per_day'])->keyBy('date');
        $this->assertSame(4, $perDay['2026-03-25']['count']);
        $this->assertSame(1, $perDay['2026-03-22']['count']);
        $this->assertSame(0, $perDay['2026-03-24']['count']);

        $this->assertSame(
            [
                ['id' => $hot->id, 'title' => 'Hot', 'bid_count' => 2],
                ['id' => $warm->id, 'title' => 'Warm', 'bid_count' => 1],
            ],
            $stats['hot_auctions']->all(),
        );

        $this->assertSame(['username' => 'alice', 'auction_count' => 3], $stats['top_bidders']->first());
        $this->assertSame(['username' => 'bob', 'auction_count' => 2], $stats['top_bidders']->get(1));
    }

    public function test_hot_auctions_and_top_bidders_are_capped_at_five(): void
    {
        $bidders = collect(range(1, 6))->map(fn() => $this->createUser());

        foreach (range(1, 6) as $i) {
            $auction = $this->createAuction(null, ['ends_at' => now()->addDay()]);
            $this->createBid($auction, $bidders[$i - 1]);
        }

        $stats = new StatsService()->getStats();

        $this->assertCount(5, $stats['hot_auctions']);
        $this->assertCount(5, $stats['top_bidders']);
    }
}
