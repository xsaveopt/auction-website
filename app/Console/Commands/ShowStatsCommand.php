<?php

namespace App\Console\Commands;

use App\Support\StatsService;
use Illuminate\Console\Command;

class ShowStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction:stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show current platform statistics';

    /**
     * Execute the console command.
     */
    public function handle(StatsService $statsService): void
    {
        $stats = $statsService->getStats();

        $this->info('Auction House Statistics');
        $this->info('------------------------');

        $rows = [
            ['Active Auctions', $stats['active_auctions']],
            ['Ended Auctions', $stats['ended_auctions']],
            ['Total Items (Active)', $stats['total_items']],
            ['Total Bids', $stats['total_bids']],
            ['Total Users', $stats['total_users']],
            ['Current Bid Total (Active)', '$' . number_format($stats['current_bid_total'], 2)],
            ['Online Users', $stats['online_users']],
        ];

        $this->table(['Metric', 'Value'], $rows);

        $this->info('');
        $this->info('Top Bidders (by auction count)');
        $this->table(
            ['Username', 'Auctions Bid On'],
            collect($stats['top_bidders'])->map(fn($b) => [$b['username'], $b['auction_count']])
        );
    }
}
