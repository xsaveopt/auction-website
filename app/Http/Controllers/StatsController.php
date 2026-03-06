<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();

        $activeAuctions = Auction::where('status', 'active')->where('ends_at', '>', $now)->count();
        $endedAuctions = Auction::where('ends_at', '<=', $now)->orWhere('status', '!=', 'active')->count();
        $totalItems = (int) Auction::where('status', 'active')->where('ends_at', '>', $now)->sum('quantity');
        $totalBids = Bid::count();
        $totalUsers = User::count();
        $totalBidValue = (float) Bid::sum(DB::raw('amount * quantity'));
        $avgBidAmount = (float) Bid::avg('amount');
        $highestBid = (float) Bid::max('amount');
        $totalStartingValue = (float) Auction::where('status', 'active')
            ->where('ends_at', '>', $now)
            ->sum(DB::raw('starting_price * quantity'));

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
            ->map(fn (Auction $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'bid_count' => $a->bids_count,
            ]);

        // Top 5 bidders by number of auctions they've bid on
        $topBidders = Bid::select('user_id', DB::raw('COUNT(DISTINCT auction_id) as auction_count'), DB::raw('COUNT(*) as bid_count'))
            ->groupBy('user_id')
            ->orderByDesc('auction_count')
            ->limit(5)
            ->with('user:id,username')
            ->get()
            ->map(fn (Bid $b) => [
                'username' => $b->user->username,
                'auction_count' => (int) $b->auction_count,
            ]);

        // Auctions ending soon (next 24h)
        $endingSoon = Auction::where('status', 'active')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addDay())
            ->withCount('bids')
            ->orderBy('ends_at')
            ->limit(3)
            ->get()
            ->map(fn (Auction $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'ends_at' => $a->ends_at->toISOString(),
                'bid_count' => $a->bids_count,
            ]);

        return response()->json([
            'stats' => [
                'active_auctions' => $activeAuctions,
                'ended_auctions' => $endedAuctions,
                'total_items' => $totalItems,
                'total_bids' => $totalBids,
                'total_users' => $totalUsers,
                'total_bid_value' => round($totalBidValue, 2),
                'avg_bid_amount' => round($avgBidAmount, 2),
                'highest_bid' => round($highestBid, 2),
                'total_starting_value' => round($totalStartingValue, 2),
                'bids_per_day' => $bidsPerDay,
                'hot_auctions' => $hotAuctions,
                'top_bidders' => $topBidders,
                'ending_soon' => $endingSoon,
            ],
        ]);
    }
}
