<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\Presence;
use App\Support\PrometheusService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke(PrometheusService $prometheus): Response
    {
        $token = config('services.metrics.token');

        if ($token && request()->bearerToken() !== $token) {
            abort(404);
        }

        $now = now();

        $activeAuctions = Auction::where('status', 'active')->where('ends_at', '>', $now)->count();
        $totalBids = Bid::count();
        $onlineUsers = Presence::onlineUsers();
        $activeBidTotal = (float) Bid::query()
            ->join('auctions', 'auctions.id', '=', 'bids.auction_id')
            ->where('auctions.status', 'active')
            ->where('auctions.ends_at', '>', $now)
            ->sum(DB::raw('bids.amount * bids.quantity'));

        $prometheus->registerGauge('active_auctions', 'Number of active auctions', $activeAuctions);
        $prometheus->registerGauge('total_bids', 'Total number of bids', $totalBids);
        $prometheus->registerGauge('online_users', 'Number of online users', $onlineUsers);
        $prometheus->registerGauge('active_bid_total', 'Total value of bids on active auctions', $activeBidTotal);

        return new Response($prometheus->renderMetrics(), 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
