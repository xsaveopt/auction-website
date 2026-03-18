<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class EndAuctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:end-auction
                            {id : The ID of the auction}
                            {--cancel : Cancel instead of ending normally}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'End or cancel an auction early';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $auction = Auction::withCount('bids')->find($this->argument('id'));

        if (!$auction) {
            $this->error("Auction with ID {$this->argument('id')} not found.");
            return;
        }

        $newStatus = $this->option('cancel') ? 'cancelled' : 'ended';

        $this->info("Auction #{$auction->id}: {$auction->title}");
        $this->table(['Field', 'Value'], [
            ['Status', $auction->status],
            ['Ends at', $auction->ends_at->toDateTimeString()],
            ['Bids', $auction->bids_count],
            ['New status', $newStatus],
        ]);

        if (!$this->confirm("Set this auction to \"{$newStatus}\"?")) {
            return;
        }

        $auction->status = $newStatus;
        $auction->ends_at = now();
        $auction->save();

        $this->info("Auction #{$auction->id} is now \"{$newStatus}\".");
    }
}
