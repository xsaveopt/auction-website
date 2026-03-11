<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\Presence;
use Illuminate\Database\Eloquent\Builder;

class AuctionService
{
    /**
     * Allocate items to bids, sorted by amount descending.
     * Returns a map of bid ID => number of items won.
     *
     * @return array{allocations: array<int, int>, clearing_price: float}
     */
    public function allocate(Auction $auction): array
    {
        $sortedBids = $auction->bids->sortByDesc('amount')->values();
        $remaining = (int) $auction->quantity;
        /** @var array<int, int> $allocations */
        $allocations = [];
        $clearingPrice = (float) $auction->starting_price;

        foreach ($sortedBids as $bid) {
            if ($remaining <= 0) {
                break;
            }

            $give = min((int) $bid->quantity, $remaining);
            $allocations[$bid->id] = $give;
            $remaining -= $give;
            $clearingPrice = (float) $bid->amount;
        }

        return [
            'allocations' => $allocations,
            'clearing_price' => $clearingPrice,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function auctionResponse(Auction $auction, bool $withBids = false): array
    {
        return $this->auctionResponseFromAllocation($auction, $this->allocate($auction), $withBids);
    }

    /**
     * @param array{allocations: array<int, int>, clearing_price: float} $result
     * @return array<string, mixed>
     */
    public function auctionResponseFromAllocation(Auction $auction, array $result, bool $withBids = false): array
    {
        $allocations = $result['allocations'];
        $clearingPrice = $result['clearing_price'];

        $data = [
            'id' => $auction->id,
            'title' => $auction->title,
            'description' => $auction->description,
            'starting_price' => $auction->starting_price,
            'current_price' => $clearingPrice,
            'quantity' => $auction->quantity,
            'max_per_bidder' => $auction->max_per_bidder,
            'ends_at' => $auction->ends_at->toISOString(),
            'status' => $auction->status,
            'is_active' => $auction->isActive(),
            'seller' => $auction->seller
                ? [
                    'id' => $auction->seller->id,
                    'username' => $auction->seller->username,
                ] : null,
            'bid_count' => $auction->bids->count(),
            'watcher_count' => $auction->watcher_count,
            'items_allocated' => array_sum($allocations),
            'images' => $auction
                ->images
                ->map(fn($img) => [
                    'id' => $img->id,
                    'url' => "/api/images/{$img->id}",
                ])
                ->values(),
            'created_at' => $auction->created_at?->toISOString(),
        ];

        if ($withBids) {
            $sortedBids = $auction->bids->sortByDesc('amount')->values();
            $data['bids'] = $sortedBids->map(fn(Bid $bid) => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'quantity' => $bid->quantity,
                'won_quantity' => $allocations[$bid->id] ?? 0,
                'user' => [
                    'id' => $bid->user?->id,
                    'username' => $bid->user?->username,
                ],
                'created_at' => $bid->created_at?->toISOString(),
            ]);
        }

        if ($auction->relationLoaded('questions')) {
            $data['questions'] = $auction
                ->questions
                ->map(fn(\App\Models\AuctionQuestion $question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer' => $question->answer,
                    'answered_at' => $question->answered_at?->toISOString(),
                    'user' => [
                        'id' => $question->user?->id,
                        'username' => $question->user?->username,
                    ],
                    'created_at' => $question->created_at?->toISOString(),
                ])
                ->values();
        }

        return $data;
    }

    /** @return Builder<Auction> */
    public function auctionQuery(): Builder
    {
        return Auction::query()
            ->select('auctions.*')
            ->addSelect([
                'watcher_count' => Presence::watcherCountSubquery(),
            ]);
    }
}
