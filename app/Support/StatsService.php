<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type BidCountData array{label: string, date: string, count: int}
 * @phpstan-type HotAuctionData array{id: int, title: string, bid_count: int}
 * @phpstan-type TopBidderData array{username: string, auction_count: int}
 * @phpstan-type EndingSoonData array{id: int, title: string, ends_at: string, bid_count: int}
 * @phpstan-type StatsData array{
 *     active_auctions: int,
 *     ended_auctions: int,
 *     total_items: int,
 *     total_bids: int,
 *     total_users: int,
 *     current_bid_total: float,
 *     online_users: int,
 *     bids_per_day: list<BidCountData>,
 *     hot_auctions: Collection<int, HotAuctionData>,
 *     top_bidders: Collection<int, TopBidderData>,
 *     ending_soon: Collection<int, EndingSoonData>
 * }
 */
class StatsService
{
    /**
     * @return StatsData
     */
    public function getStats(): array
    {
        $now = now();

        $activeAuctions = Auction::where('status', 'active')->where('ends_at', '>', $now)->count();
        $endedAuctions = Auction::where('ends_at', '<=', $now)->orWhere('status', '!=', 'active')->count();
        $totalItems = (int) Auction::where('status', 'active')->where('ends_at', '>', $now)->sum('quantity');
        $totalBids = Bid::count();
        $totalUsers = User::count();
        $currentBidTotal = (float) Bid::query()
            ->join('auctions', 'auctions.id', '=', 'bids.auction_id')
            ->where('auctions.status', 'active')
            ->where('auctions.ends_at', '>', $now)
            ->sum(DB::raw('bids.amount * bids.quantity'));

        // Bids per day for the last 7 days
        $bidsPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $count = Bid::whereDate('created_at', $date->toDateString())->count();
            $bidsPerDay[] = [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'count' => $count,
            ];
        }

        // Top 5 most competitive auctions (by bid count, active only)
        $hotAuctions = Auction::where('status', 'active')
            ->where('ends_at', '>', $now)
            ->withCount('bids')
            ->orderByDesc('bids_count')
            ->limit(5)
            ->get()
            ->map(fn(Auction $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'bid_count' => (int) $a->bids_count,
            ]);

        // Top 5 bidders by number of auctions they've bid on
        $topBidders = Bid::select(
            'user_id',
            DB::raw('COUNT(DISTINCT auction_id) as auction_count'),
            DB::raw('COUNT(*) as bid_count'),
        )
            ->groupBy('user_id')
            ->orderByDesc('auction_count')
            ->limit(5)
            ->with('user:id,username')
            ->get()
            ->map(fn(Bid $b) => [
                'username' => $b->user->username ?? '',
                'auction_count' => intval($b->auction_count),
            ]);

        // Auctions ending soon (next 24h)
        $endingSoon = Auction::where('status', 'active')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addDay())
            ->withCount('bids')
            ->orderBy('ends_at')
            ->limit(3)
            ->get()
            ->map(fn(Auction $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'ends_at' => $a->ends_at->toIso8601String(),
                'bid_count' => (int) $a->bids_count,
            ]);

        return [
            'active_auctions' => $activeAuctions,
            'ended_auctions' => $endedAuctions,
            'total_items' => $totalItems,
            'total_bids' => $totalBids,
            'total_users' => $totalUsers,
            'current_bid_total' => round($currentBidTotal, 2),
            'online_users' => Presence::onlineUsers(),
            'bids_per_day' => $bidsPerDay,
            'hot_auctions' => $hotAuctions,
            'top_bidders' => $topBidders,
            'ending_soon' => $endingSoon,
        ];
    }
}
