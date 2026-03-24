<?php

namespace App\Console\Commands;

use App\Support\AuctionFinalizationService;
use Illuminate\Console\Command;

class FinalizeEndedAuctionsCommand extends Command
{
    protected $signature = 'app:finalize-ended-auctions';

    protected $description = 'Mark expired auctions as ended and send bidder push notifications';

    public function __construct(
        protected AuctionFinalizationService $auctionFinalizationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->auctionFinalizationService->finalizeExpiredAuctions();

        $this->info("Finalized {$count} expired auction(s).");

        return self::SUCCESS;
    }
}
