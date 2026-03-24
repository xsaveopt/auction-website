<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\Presence;

class AuctionService
{
    /**
     * Allocate items to bids, sorted by amount descending.
     * Returns a map of bid ID => number of items won.
     *
     * Pricing rule: if the lowest winner received their full requested quantity,
     * all winners pay the clearing price (uniform). Otherwise each winner pays
     * their own bid amount (pay-your-bid).
     *
     * @return array{allocations: array<int, int>, clearing_price: float, prices: array<int, float>}
     */
    public function allocate(Auction $auction): array
    {
        /** @var \Illuminate\Support\Collection<int, Bid> $latestBids */
        $latestBids = $auction
            ->bids
            ->groupBy('user_id')
            ->map(fn($userBids) => $userBids->sortByDesc('id')->first())
            ->values();
        $sortedBids = $latestBids->sortBy([
            ['amount', 'desc'],
            ['quantity', 'desc'],
        ])->values();
        $remaining = (int) $auction->quantity;
        /** @var array<int, int> $allocations */
        $allocations = [];
        $clearingPrice = (float) $auction->starting_price;
        /** @var int|null $lastWinnerIndex */
        $lastWinnerIndex = null;

        foreach ($sortedBids as $index => $bid) {
            if ($remaining <= 0) {
                break;
            }

            $give = min((int) $bid->quantity, $remaining);
            $allocations[$bid->id] = $give;
            $remaining -= $give;
            $clearingPrice = (float) $bid->amount;
            $lastWinnerIndex = $index;
        }

        // Determine pricing mode: uniform if the lowest winner got everything they wanted
        $uniform = true;
        if ($lastWinnerIndex !== null) {
            /** @var Bid $lastWinner */
            $lastWinner = $sortedBids[$lastWinnerIndex];
            $uniform = $allocations[$lastWinner->id] >= (int) $lastWinner->quantity;
        }

        /** @var array<int, float> $prices */
        $prices = [];
        foreach ($sortedBids as $bid) {
            if (!isset($allocations[$bid->id])) {
                break;
            }
            $prices[$bid->id] = $uniform ? $clearingPrice : (float) $bid->amount;
        }

        return [
            'allocations' => $allocations,
            'clearing_price' => $clearingPrice,
            'prices' => $prices,
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
     * @param array{allocations: array<int, int>, clearing_price: float, prices: array<int, float>} $result
     * @return array<string, mixed>
     */
    public function auctionResponseFromAllocation(Auction $auction, array $result, bool $withBids = false): array
    {
        $allocations = $result['allocations'];
        $clearingPrice = $result['clearing_price'];
        $prices = $result['prices'];

        $data = [
            'id' => $auction->id,
            'title' => $auction->title,
            'description' => $auction->description,
            'starting_price' => $auction->starting_price,
            'current_price' => $clearingPrice,
            'quantity' => $auction->quantity,
            'max_per_bidder' => $auction->max_per_bidder,
            'ends_at' => $auction->ends_at->format('Y-m-d\TH:i:sP'),
            'status' => $auction->status,
            'is_active' => $auction->isActive(),
            'seller' => $auction->seller
                ? [
                    'id' => $auction->seller->id,
                    'username' => $auction->seller->username,
                ] : null,
            'bid_count' => $auction->bids->unique('user_id')->count(),
            'watcher_count' => $auction->watcher_count,
            'items_allocated' => array_sum($allocations),
            'images' => $auction
                ->images
                ->map(fn($img) => [
                    'id' => $img->id,
                    'url' => "/api/images/{$img->id}",
                ])
                ->values(),
            'category_id' => $auction->category_id,
            'category' => $auction->relationLoaded('category') && $auction->category
                ? [
                    'id' => $auction->category->id,
                    'name' => $auction->category->name,
                    'slug' => $auction->category->slug,
                ] : null,
            'created_at' => $auction->created_at?->format('Y-m-d\TH:i:sP'),
        ];

        if ($withBids) {
            /** @var \Illuminate\Support\Collection<int, Bid> $latestBids */
            $latestBids = $auction
                ->bids
                ->groupBy('user_id')
                ->map(fn($userBids) => $userBids->sortByDesc('id')->first())
                ->values();
            $sortedBids = $latestBids->sortBy([
                ['amount', 'desc'],
                ['quantity', 'desc'],
            ])->values();
            $data['bids'] = $sortedBids->map(fn(Bid $bid) => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'quantity' => $bid->quantity,
                'won_quantity' => $allocations[$bid->id] ?? 0,
                'price' => isset($prices[$bid->id]) ? number_format($prices[$bid->id], 2, '.', '') : null,
                'user' => [
                    'id' => $bid->user?->id,
                    'username' => $bid->user?->username,
                ],
                'created_at' => $bid->created_at?->format('Y-m-d\TH:i:sP'),
            ]);
        }

        if ($auction->relationLoaded('questions')) {
            $data['questions'] = $auction
                ->questions
                ->map(fn(\App\Models\AuctionQuestion $question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer' => $question->answer,
                    'answered_at' => $question->answered_at?->format('Y-m-d\TH:i:sP'),
                    'user' => [
                        'id' => $question->user?->id,
                        'username' => $question->user?->username,
                    ],
                    'created_at' => $question->created_at?->format('Y-m-d\TH:i:sP'),
                ])
                ->values();
        }

        return $data;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions
     */
    public function loadWatcherCounts(\Illuminate\Database\Eloquent\Collection $auctions): void
    {
        /** @var array<int, int> $auctionIds */
        $auctionIds = $auctions->pluck('id')->all();
        $counts = Presence::watcherCountsForAuctions($auctionIds);

        $auctions->each(function (Auction $auction) use ($counts) {
            $auction->setAttribute('watcher_count', (int) ($counts[$auction->id] ?? 0));
        });
    }
}
