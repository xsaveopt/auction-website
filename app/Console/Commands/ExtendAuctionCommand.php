<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class ExtendAuctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:extend-auction
                            {id : The auction ID}
                            {time : Time to add (e.g. "+1 hour", "+30 minutes", "+2 days")}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extend an auction\'s end time';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $auction = Auction::find($this->argument('id'));

        if (!$auction) {
            $this->error("Auction with ID {$this->argument('id')} not found.");
            return;
        }

        $oldEnd = $auction->ends_at->copy();

        try {
            $newEnd = $oldEnd->copy()->modify((string) $this->argument('time'));
        } catch (\Exception) {
            $this->error("Invalid time modifier: {$this->argument('time')}");
            return;
        }

        $this->table(['Field', 'Value'], [
            ['Auction', "#{$auction->id}: {$auction->title}"],
            ['Status', $auction->status],
            ['Current end', $oldEnd->toDateTimeString()],
            ['New end', $newEnd->toDateTimeString()],
        ]);

        if (!$this->confirm('Apply this change?')) {
            return;
        }

        $auction->ends_at = $newEnd;
        $auction->save();

        $this->info("Auction #{$auction->id} now ends at {$newEnd->toDateTimeString()}.");
    }
}
