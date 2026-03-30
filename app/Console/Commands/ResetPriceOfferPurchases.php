<?php

namespace App\Console\Commands;

use App\Models\LeftoverPurchase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:reset-price-offer-purchases')]
#[Description('Delete leftover_purchases rows created from price offers and reset those offers to pending')]
class ResetPriceOfferPurchases extends Command
{
    public function handle(): int
    {
        $purchases = LeftoverPurchase::withTrashed()->whereNotNull('leftover_price_offer_id')->get();

        if ($purchases->isEmpty()) {
            $this->info('No price-offer-based purchases found.');
            return 0;
        }

        $this->table(['Purchase ID', 'Offer ID', 'User ID', 'Auction ID', 'Qty', 'Price'], $purchases->map(fn($p) => [
            $p->id,
            $p->leftover_price_offer_id,
            $p->user_id,
            $p->auction_id,
            $p->quantity,
            $p->price_per_item,
        ]));

        if (!$this->confirm('Delete these purchases and reset their offers to pending?')) {
            return 1;
        }

        DB::transaction(function () use ($purchases) {
            $offerIds = $purchases->pluck('leftover_price_offer_id')->filter()->unique();

            LeftoverPurchase::withTrashed()->whereNotNull('leftover_price_offer_id')->forceDelete();

            \App\Models\LeftoverPriceOffer::whereIn('id', $offerIds)->update(['status' => 'pending']);
        });

        $this->info("Deleted {$purchases->count()} purchase(s) and reset {$purchases->count()} offer(s) to pending.");

        return 0;
    }
}
