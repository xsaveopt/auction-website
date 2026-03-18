<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use Illuminate\Console\Command;

class ExportAuctionResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-results
                            {id? : Export a specific auction (omit for all ended)}
                            {--output=php://stdout : Output file path (default: stdout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export auction results to CSV';

    /**
     * Execute the console command.
     */
    public function handle(AuctionService $auctionService): void
    {
        $auctionId = $this->argument('id');

        if ($auctionId) {
            $auctions = Auction::with('bids.user')->where('id', $auctionId)->get();
            if ($auctions->isEmpty()) {
                $this->error("Auction with ID {$auctionId} not found.");
                return;
            }
        } else {
            $auctions = Auction::with('bids.user')->where('status', 'ended')->get();
            if ($auctions->isEmpty()) {
                $this->info('No ended auctions found.');
                return;
            }
        }

        /** @var string $outputPath */
        $outputPath = $this->option('output');
        $handle = fopen($outputPath, 'w');

        if ($handle === false) {
            $this->error("Cannot open \"{$outputPath}\" for writing.");
            return;
        }

        $currency = BiddingSchedule::currencySymbol();

        fputcsv($handle, [
            'Auction ID',
            'Title',
            'Status',
            'User',
            'Bid Amount',
            'Requested Qty',
            'Won Qty',
            'Price/Item',
            'Total',
        ]);

        foreach ($auctions as $auction) {
            if ($auction->bids->isEmpty()) {
                fputcsv($handle, [$auction->id, $auction->title, $auction->status, '', '', '', '', '', '']);
                continue;
            }

            $result = $auctionService->allocate($auction);
            $allocations = $result['allocations'];
            $prices = $result['prices'];

            $sortedBids = $auction->bids->sortBy([['amount', 'desc'], ['quantity', 'desc']])->values();

            foreach ($sortedBids as $bid) {
                $won = $allocations[$bid->id] ?? 0;
                $price = $prices[$bid->id] ?? null;
                $total = $price !== null ? $won * $price : null;

                fputcsv($handle, [
                    $auction->id,
                    $auction->title,
                    $auction->status,
                    $bid->user->username ?? 'Unknown',
                    number_format((float) $bid->amount, 2, '.', ''),
                    $bid->quantity,
                    $won,
                    $price !== null ? number_format($price, 2, '.', '') : '',
                    $total !== null ? number_format($total, 2, '.', '') : '',
                ]);
            }
        }

        fclose($handle);

        if ($outputPath !== 'php://stdout') {
            $this->info("Exported {$auctions->count()} auction(s) to {$outputPath}");
        }
    }
}
