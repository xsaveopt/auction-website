<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class ReactivateAuctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reactivate-auction
                            {id : The auction ID}
                            {--ends-at= : New end time (Y-m-d H:i:s), defaults to +24 hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reactivate an ended or cancelled auction';

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

        if ($auction->status === 'active') {
            $this->warn("Auction #{$auction->id} is already active.");
            return;
        }

        $endsAt = $this->option('ends-at')
            ? \Illuminate\Support\Carbon::parse($this->option('ends-at'))
            : now()->addHours(24);

        $this->table(['Field', 'Value'], [
            ['Auction', "#{$auction->id}: {$auction->title}"],
            ['Current status', $auction->status],
            ['Bids', $auction->bids_count],
            ['New status', 'active'],
            ['New end time', $endsAt->toDateTimeString()],
        ]);

        if (!$this->confirm('Reactivate this auction?')) {
            return;
        }

        $auction->status = 'active';
        $auction->ends_at = $endsAt;
        $auction->save();

        $this->info("Auction #{$auction->id} is now active, ending at {$endsAt->toDateTimeString()}.");
    }
}
