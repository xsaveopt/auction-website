<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Support\AuctionNotificationService;
use Illuminate\Console\Command;

class NotifyEndingSoonCommand extends Command
{
    protected $signature = 'app:notify-ending-soon {--minutes=15 : Notify bidders on auctions ending within this many minutes}';

    protected $description = 'Send push notifications to bidders on auctions ending soon';

    public function __construct(
        protected AuctionNotificationService $auctionNotificationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->addMinutes($minutes);

        $auctions = Auction::query()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', $cutoff)
            ->where('ending_soon_notified', false)
            ->with('bids.user:id,username')
            ->get();

        foreach ($auctions as $auction) {
            $this->auctionNotificationService->sendEndingSoonNotifications($auction);
            $auction->update(['ending_soon_notified' => true]);
        }

        $this->info("Sent ending-soon notifications for {$auctions->count()} auction(s).");

        return self::SUCCESS;
    }
}
