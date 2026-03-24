<?php

namespace App\Support;

use App\Models\Auction;
use Illuminate\Support\Carbon;

class AuctionFinalizationService
{
    public function __construct(
        protected AuctionNotificationService $auctionNotificationService,
    ) {}

    public function end(Auction $auction): bool
    {
        return $this->transition($auction, 'ended', now());
    }

    public function cancel(Auction $auction): bool
    {
        return $this->transition($auction, 'cancelled', now());
    }

    public function finalizeExpiredAuctions(): int
    {
        $timestamp = now();
        $count = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = Auction::query()
            ->with('bids.user:id,username')
            ->where('status', 'active')
            ->where('ends_at', '<=', $timestamp)
            ->get();

        foreach ($auctions as $auction) {
            if ($this->transition($auction, 'ended', $timestamp, false)) {
                $count++;
            }
        }

        return $count;
    }

    private function transition(Auction $auction, string $status, Carbon $endsAt, bool $loadMissing = true): bool
    {
        if ($auction->status !== 'active') {
            return false;
        }

        if ($loadMissing) {
            $auction->loadMissing('bids.user:id,username');
        }

        $auction->status = $status;
        $auction->ends_at = $endsAt;
        $auction->save();

        $this->auctionNotificationService->sendAuctionClosedNotifications($auction);

        return true;
    }
}
