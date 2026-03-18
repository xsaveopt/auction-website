<?php

namespace App\Console\Commands;

use App\Models\Bid;
use Illuminate\Console\Command;

class UpdateBidCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-bid
                            {id : The ID of the bid to update}
                            {--amount= : New bid amount}
                            {--quantity= : New bid quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a bid\'s amount and/or quantity';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $bid = Bid::with(['user', 'auction'])->find($this->argument('id'));

        if (!$bid) {
            $this->error("Bid with ID {$this->argument('id')} not found.");
            return;
        }

        $amount = $this->option('amount');
        $quantity = $this->option('quantity');

        if ($amount === null && $quantity === null) {
            $this->error('You must specify at least one of --amount or --quantity.');
            return;
        }

        $auction = $bid->auction;
        $user = $bid->user;

        $this->info("Bid #{$bid->id} on \"{$auction?->title}\" by {$user?->username}");
        $this->table(['Field', 'Current', 'New'], [
            ['Amount', $bid->amount, $amount ?? $bid->amount],
            ['Quantity', $bid->quantity, $quantity ?? $bid->quantity],
        ]);

        if (!$this->confirm('Apply these changes?')) {
            return;
        }

        if ($amount !== null) {
            $bid->amount = $amount;
        }
        if ($quantity !== null) {
            $bid->quantity = (int) $quantity;
        }

        $bid->save();

        $this->info("Bid #{$bid->id} updated successfully.");
    }
}
