<?php

namespace App\Console\Commands;

use App\Models\Bid;
use Illuminate\Console\Command;

class DeleteBidCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-bid
                            {id? : The ID of a specific bid to delete}
                            {--user= : Delete all bids by this user (username)}
                            {--auction= : Delete all bids for this auction (ID)}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete bids by ID, user, or auction';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $bidId = $this->argument('id');
        $username = $this->option('user');
        $auctionId = $this->option('auction');

        if (!$bidId && !$username && !$auctionId) {
            $this->error('You must specify a bid ID, --user, or --auction.');
            return;
        }

        $query = Bid::with(['user', 'auction']);

        if ($bidId) {
            $query->where('id', $bidId);
        }
        if ($username) {
            $query->whereHas('user', fn($q) => $q->where('username', $username));
        }
        if ($auctionId) {
            $query->where('auction_id', $auctionId);
        }

        $bids = $query->get();

        if ($bids->isEmpty()) {
            $this->info('No bids found matching criteria.');
            return;
        }

        $rows = $bids->map(fn(Bid $bid) => [
            $bid->id,
            $bid->user->username ?? 'Unknown',
            $bid->auction->title ?? 'Unknown',
            $bid->amount,
            $bid->quantity,
        ]);

        $this->table(['Bid ID', 'User', 'Auction', 'Amount', 'Quantity'], $rows);

        if (!$this->option('force') && !$this->confirm("Delete {$bids->count()} bid(s)?")) {
            return;
        }

        Bid::whereIn('id', $bids->pluck('id'))->delete();

        $this->info("Deleted {$bids->count()} bid(s).");
    }
}
