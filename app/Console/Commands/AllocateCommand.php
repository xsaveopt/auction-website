<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use Illuminate\Console\Command;

class AllocateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:allocate
                            {id : The auction ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show allocation results for an auction (who won what at what price)';

    /**
     * Execute the console command.
     */
    public function handle(AuctionService $auctionService): void
    {
        $auction = Auction::with('bids.user')->find($this->argument('id'));

        if (!$auction) {
            $this->error("Auction with ID {$this->argument('id')} not found.");
            return;
        }

        $this->info("Auction #{$auction->id}: {$auction->title}");
        $this->info("Status: {$auction->status} | Quantity: {$auction->quantity} | Bids: {$auction->bids->count()}");
        $this->newLine();

        if ($auction->bids->isEmpty()) {
            $this->warn('No bids on this auction.');
            return;
        }

        $result = $auctionService->allocate($auction);
        $allocations = $result['allocations'];
        $clearingPrice = $result['clearing_price'];
        $prices = $result['prices'];
        $currency = BiddingSchedule::currencySymbol();

        $allocated = array_sum($allocations);
        $this->info("Clearing price: {$currency} " . number_format($clearingPrice, 2, ',', '.'));
        $this->info("Items allocated: {$allocated} / {$auction->quantity}");
        $this->newLine();

        $rows = $auction
            ->bids
            ->sortBy([['amount', 'desc'], ['quantity', 'desc']])
            ->values()
            ->map(fn($bid) => [
                $bid->user->username ?? 'Unknown',
                "{$currency} " . number_format((float) $bid->amount, 2, ',', '.'),
                $bid->quantity,
                $allocations[$bid->id] ?? 0,
                isset($prices[$bid->id]) ? "{$currency} " . number_format($prices[$bid->id], 2, ',', '.') : '-',
                isset($prices[$bid->id])
                    ? "{$currency} " . number_format(($allocations[$bid->id] ?? 0) * $prices[$bid->id], 2, ',', '.')
                    : '-',
            ]);

        $this->table(['User', 'Bid', 'Requested', 'Won', 'Price/item', 'Total'], $rows);

        $totalRevenue = 0.0;
        foreach ($allocations as $bidId => $qty) {
            if (isset($prices[$bidId])) {
                $totalRevenue += $qty * $prices[$bidId];
            }
        }

        $this->info("Total revenue: {$currency} " . number_format($totalRevenue, 2, ',', '.'));
    }
}
