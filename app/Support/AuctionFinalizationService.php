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
            if ($this->transition($auction, 'ended', $timestamp, false, $timestamp)) {
                $count++;
            }
        }

        return $count;
    }

    private function transition(
        Auction $auction,
        string $status,
        Carbon $endsAt,
        bool $loadMissing = true,
        ?Carbon $expiredBy = null,
    ): bool {
        $claimed = Auction::query()
            ->whereKey($auction->getKey())
            ->where('status', 'active')
            ->when($expiredBy !== null, fn($query) => $query->where('ends_at', '<=', $expiredBy))
            ->update([
                'status' => $status,
                'ends_at' => $endsAt,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $auction->refresh();

        if ($loadMissing) {
            $auction->loadMissing('bids.user:id,username');
        }

        $this->auctionNotificationService->sendAuctionClosedNotifications($auction);

        return true;
    }
}
