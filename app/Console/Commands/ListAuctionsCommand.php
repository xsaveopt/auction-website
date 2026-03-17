<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class ListAuctionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:list-auctions
                            {--status= : Filter by status (active, ended, cancelled)}
                            {--search= : Filter by title}
                            {--limit=20 : Number of auctions to display}';

    /**
     * @var string
     */
    protected $description = 'List auctions';

    public function handle(): void
    {
        $query = Auction::query()->withCount('bids');

        /** @var string|null $status */
        $status = $this->option('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        /** @var string|null $search */
        $search = $this->option('search');
        if (is_string($search) && $search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $auctions = $query->latest()->limit((int) $this->option('limit'))->get();

        if ($auctions->isEmpty()) {
            $this->info('No auctions found.');

            return;
        }

        $rows = $auctions->map(fn(Auction $auction): array => [
            $auction->id,
            mb_substr($auction->title, 0, 40),
            $auction->status,
            $auction->starting_price,
            $auction->quantity,
            $auction->bids_count,
            $auction->ends_at->format('Y-m-d H:i'),
        ]);

        $this->table(['ID', 'Title', 'Status', 'Start Price', 'Qty', 'Bids', 'Ends At'], $rows);
        $this->info("Showing {$auctions->count()} auctions.");
    }
}
