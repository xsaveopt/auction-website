<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\AuctionService;
use Illuminate\Console\Command;

class ListAuctionBidsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:list-bids
                            {id : The ID of the auction}
                            {--limit=20 : Number of bids to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List bids for a specific auction';

    /**
     * Execute the console command.
     */
    public function handle(AuctionService $auctionService): void
    {
        $auctionId = $this->argument('id');
        $auction = Auction::find($auctionId);

        if (!$auction) {
            $this->error("Auction with ID {$auctionId} not found.");
            return;
        }

        $this->info("Bids for auction: {$auction->title} (ID: {$auction->id})");
        $this->info('Current Price: ' . $auctionService->allocate($auction)['clearing_price']);

        $bids = Bid::with('user')
            ->where('auction_id', $auctionId)
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        if ($bids->isEmpty()) {
            $this->info('No bids found.');
            return;
        }

        $rows = $bids->map(function (Bid $bid): array {
            return [
                $bid->id,
                $bid->user->username ?? 'Unknown',
                $bid->amount,
                $bid->quantity,
                $bid->created_at?->toDateTimeString() ?? 'Unknown',
            ];
        });

        $this->table(['Bid ID', 'User', 'Amount', 'Quantity', 'Date'], $rows);
    }
}
