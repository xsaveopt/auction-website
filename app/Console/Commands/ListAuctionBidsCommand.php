<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Console\Command;

class ListAuctionBidsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction:bids 
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
    public function handle(): void
    {
        $auctionId = $this->argument('id');
        $auction = Auction::find($auctionId);

        if (!$auction) {
            $this->error("Auction with ID {$auctionId} not found.");
            return;
        }

        $this->info("Bids for auction: {$auction->title} (ID: {$auction->id})");
        $this->info("Current Price: " . $auction->currentPrice());

        $bids = Bid::with('user')
            ->where('auction_id', $auctionId)
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        if ($bids->isEmpty()) {
            $this->info('No bids found.');
            return;
        }

        $rows = $bids->map(function ($bid) {
            return [
                $bid->id,
                $bid->user->username ?? 'Unknown',
                $bid->amount,
                $bid->quantity,
                $bid->created_at->toDateTimeString(),
            ];
        });

        $this->table(['Bid ID', 'User', 'Amount', 'Quantity', 'Date'], $rows);
    }
}
