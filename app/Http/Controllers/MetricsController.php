<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionQuestion;
use App\Models\Bid;
use App\Models\LeftoverPurchase;
use App\Models\User;
use App\Support\AuctionService;
use App\Support\Presence;
use App\Support\PrometheusService;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke(PrometheusService $prometheus, AuctionService $auctionService): Response
    {
        $token = config('services.metrics.token');

        if (!$token || request()->bearerToken() !== $token) {
            abort(404);
        }

        $now = now();

        // Load all non-cancelled auctions with bids; filter active ones in memory
        $allAuctionsWithBids = Auction::whereIn('status', ['active', 'ended'])->with('bids.user:id,username')->get();

        $activeAuctionsList = $allAuctionsWithBids
            ->filter(fn($a) => $a->status === 'active' && $a->ends_at > $now)
            ->values();

        $activeAuctions = $activeAuctionsList->count();
        $totalBids = Bid::count();
        $onlineUsers = Presence::onlineUsers();

        // Calculate winning bid total using per-bid pricing from AuctionService
        $winningBidTotal = 0.0;
        foreach ($activeAuctionsList as $auction) {
            $result = $auctionService->allocate($auction);
            foreach ($result['allocations'] as $bidId => $qty) {
                $winningBidTotal += ($result['prices'][$bidId] ?? 0.0) * $qty;
            }
        }

        $totalItems = (int) Auction::where('status', 'active')->where('ends_at', '>', $now)->sum('quantity');
        $registeredUsers = User::count();

        $prometheus->registerGauge('active_auctions', 'Number of active auctions', $activeAuctions);
        $prometheus->registerGauge('total_bids', 'Total number of bids', $totalBids);
        $prometheus->registerGauge('online_users', 'Number of online users', $onlineUsers);
        $prometheus->registerGauge('total_items', 'Total items up for grabs on active auctions', $totalItems);
        $prometheus->registerGauge('registered_users', 'Total registered users', $registeredUsers);
        $prometheus->registerGauge(
            'winning_bid_total',
            'Total value of winning bids on active auctions',
            $winningBidTotal,
        );
        $prometheus->registerGauge(
            'questions_unanswered',
            'Number of unanswered auction questions',
            AuctionQuestion::whereNull('answer')->count(),
        );

        $output = $prometheus->renderMetrics();

        // Online users (per-user presence)
        $onlineUserDetails = Presence::onlineUserDetails();
        $output .= "# HELP app_online_user_last_seen Last-seen timestamp in milliseconds for online users\n";
        $output .= "# TYPE app_online_user_last_seen gauge\n";
        foreach ($onlineUserDetails as $detail) {
            $username = self::escapeLabel($detail['username']);
            $pageType = self::escapeLabel($detail['page_type']);
            $output .= "app_online_user_last_seen{username=\"{$username}\",page_type=\"{$pageType}\"} {$detail['last_seen_at']}\n";
        }

        // Recent user signups (last 25)
        $recentUsers = User::orderByDesc('created_at')->limit(25)->get(['username', 'created_at']);
        $output .= "# HELP app_user_signup_timestamp User signup timestamp in milliseconds\n";
        $output .= "# TYPE app_user_signup_timestamp gauge\n";
        foreach ($recentUsers as $user) {
            $username = self::escapeLabel($user->username);
            $ts = ($user->created_at?->getTimestamp() ?? 0) * 1000;
            $output .= "app_user_signup_timestamp{username=\"{$username}\"} {$ts}\n";
        }

        // Bids (all non-cancelled auctions) and instant buys
        $output .= "# HELP app_auction_bid_info Bid and instant buy info, value is timestamp in milliseconds\n";
        $output .= "# TYPE app_auction_bid_info gauge\n";
        foreach ($allAuctionsWithBids as $auction) {
            $auctionTitle = self::escapeLabel($auction->title);
            foreach ($auction->bids->sortByDesc('amount') as $bid) {
                /** @var User $bidUser */
                $bidUser = $bid->user;
                $username = self::escapeLabel($bidUser->username);
                $amount = number_format((float) $bid->amount, 2, '.', '');
                $qty = $bid->quantity;
                $ts = ($bid->updated_at?->getTimestamp() ?? 0) * 1000;
                $output .= "app_auction_bid_info{auction=\"{$auctionTitle}\",username=\"{$username}\",amount=\"{$amount}\",quantity=\"{$qty}\",type=\"bid\"} {$ts}\n";
            }
        }

        $allPurchases = LeftoverPurchase::with(['auction:id,title', 'user:id,username'])->get();
        foreach ($allPurchases as $purchase) {
            /** @var Auction $purchaseAuction */
            $purchaseAuction = $purchase->auction;
            /** @var User $purchaseUser */
            $purchaseUser = $purchase->user;
            $auctionTitle = self::escapeLabel($purchaseAuction->title);
            $username = self::escapeLabel($purchaseUser->username);
            $amount = number_format((float) $purchase->price_per_item, 2, '.', '');
            $qty = $purchase->quantity;
            $ts = ($purchase->created_at?->getTimestamp() ?? 0) * 1000;
            $type = $purchase->leftover_price_offer_id !== null ? 'price_offer' : 'buy';
            $output .= "app_auction_bid_info{auction=\"{$auctionTitle}\",username=\"{$username}\",amount=\"{$amount}\",quantity=\"{$qty}\",type=\"{$type}\"} {$ts}\n";
        }

        return new Response($output, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }

    private static function escapeLabel(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
