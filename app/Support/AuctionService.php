<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\LeftoverPriceOffer;
use App\Models\LeftoverPurchase;
use App\Models\SiteSetting;
use App\Support\Presence;
use Illuminate\Support\Collection;

class AuctionService
{
    /**
     * @return Collection<int, Bid>
     */
    public function latestBids(Auction $auction): Collection
    {
        /** @var Collection<int, Bid> $latestBids */
        $latestBids = $auction
            ->bids
            ->groupBy('user_id')
            ->map(fn($userBids) => $userBids->sortByDesc('id')->first())
            ->filter(fn($bid) => $bid instanceof Bid)
            ->values();

        return $latestBids;
    }

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
        $latestBids = $this->latestBids($auction);
        $sortedBids = $latestBids->sortBy([
            ['amount', 'desc'],
            ['quantity', 'desc'],
            ['id', 'asc'],
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
     * @param array{allocations: array<int, int>, clearing_price: float, prices: array<int, float>}|null $result
     * @return array<int, int>
     */
    public function allocationByUser(Auction $auction, ?array $result = null): array
    {
        $result ??= $this->allocate($auction);
        $userAllocations = [];

        foreach ($this->latestBids($auction) as $bid) {
            $userAllocations[$bid->user_id] = $result['allocations'][$bid->id] ?? 0;
        }

        return $userAllocations;
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

        $settings = SiteSetting::instance();
        $itemsAllocated = array_sum($allocations);
        $leftoverSold = $this->leftoverSoldQuantity($auction);
        $leftoverQuantity = max(0, (int) $auction->quantity - $itemsAllocated - $leftoverSold);
        $leftoverPriceFactor = $settings->leftover_price_factor ?? 0.75;
        $leftoverPrice = round((float) $auction->starting_price * $leftoverPriceFactor, 2);

        $data = [
            'id' => $auction->id,
            'title' => $auction->title,
            'description' => $auction->description,
            'location' => $auction->location,
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
            'items_allocated' => $itemsAllocated,
            'leftover_enabled' => $settings->leftover_sales_enabled,
            'leftover_quantity' => $leftoverQuantity,
            'leftover_price' => number_format($leftoverPrice, 2, '.', ''),
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
            'round' => $auction->relationLoaded('round') && $auction->round
                ? [
                    'id' => $auction->round->id,
                    'name' => $auction->round->name,
                    'status' => $auction->round->status,
                ] : null,
            'created_at' => $auction->created_at?->format('Y-m-d\TH:i:sP'),
        ];

        if ($withBids) {
            $latestBids = $this->latestBids($auction);
            $sortedBids = $latestBids->sortBy([
                ['amount', 'desc'],
                ['quantity', 'desc'],
                ['id', 'asc'],
            ])->values();

            $currentUser = auth()->user();
            /** @var bool $isAdmin */
            $isAdmin = $currentUser && $currentUser->is_admin;

            $data['bids'] = $sortedBids->map(function (Bid $bid) use ($allocations, $prices) {
                return [
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
                ];
            });

            if ($auction->relationLoaded('leftoverPurchases')) {
                $data['leftover_purchases'] = $auction->leftoverPurchases->map(function (LeftoverPurchase $purchase) {
                    return [
                        'id' => $purchase->id,
                        'quantity' => $purchase->quantity,
                        'price_per_item' => $purchase->price_per_item,
                        'from_price_offer' => $purchase->leftover_price_offer_id !== null,
                        'user' => [
                            'id' => $purchase->user?->id,
                            'username' => $purchase->user?->username,
                        ],
                        'created_at' => $purchase->created_at?->format('Y-m-d\TH:i:sP'),
                    ];
                })->values();
            }

            if ($auction->relationLoaded('leftoverPriceOffers')) {
                // Admins see all offers; regular users only see their own
                $offers = $isAdmin
                    ? $auction->leftoverPriceOffers
                    : $auction->leftoverPriceOffers->filter(
                        fn(LeftoverPriceOffer $o) => $currentUser && $o->user_id === $currentUser->id,
                    );

                $data['leftover_price_offers'] = $offers->map(fn(LeftoverPriceOffer $offer) => [
                    'id' => $offer->id,
                    'quantity' => $offer->quantity,
                    'offered_price_per_item' => $offer->offered_price_per_item,
                    'status' => $offer->status,
                    'rebid_requested_at' => $offer->rebid_requested_at?->format('Y-m-d\TH:i:sP'),
                    'user' => [
                        'id' => $offer->user?->id,
                        'username' => $offer->user?->username,
                    ],
                    'created_at' => $offer->created_at?->format('Y-m-d\TH:i:sP'),
                ])->values();
            }
        }

        if ($auction->relationLoaded('questions')) {
            $currentUser = auth()->user();
            /** @var bool $isAdmin */
            $isAdmin = $currentUser && $currentUser->is_admin;

            $data['questions'] = $auction->questions->map(function (\App\Models\AuctionQuestion $question) use (
                $currentUser,
                $isAdmin,
            ) {
                $isOwner = $currentUser && $question->user_id === $currentUser->id;
                $username = $isAdmin || $isOwner ? $question->user?->username : "User #{$question->user_id}";

                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer' => $question->answer,
                    'answered_at' => $question->answered_at?->format('Y-m-d\TH:i:sP'),
                    'user' => [
                        'id' => $question->user?->id,
                        'username' => $username,
                    ],
                    'created_at' => $question->created_at?->format('Y-m-d\TH:i:sP'),
                ];
            })->values();
        }

        return $data;
    }

    public function leftoverSoldQuantity(Auction $auction): int
    {
        $fromPurchases = $auction->relationLoaded('leftoverPurchases')
            ? (int) $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity)
            : (int) $auction->leftoverPurchases()->sum('quantity');

        $fromOffers = $auction->relationLoaded('leftoverPriceOffers')
            ? (int) $auction
                ->leftoverPriceOffers
                ->where('status', 'accepted')
                ->sum(fn(LeftoverPriceOffer $o) => $o->quantity)
            : (int) $auction->leftoverPriceOffers()->where('status', 'accepted')->sum('quantity');

        return $fromPurchases + $fromOffers;
    }

    /**
     * Reject all pending price offers for an auction when no leftover stock remains.
     */
    public function closePendingOffersIfSoldOut(Auction $auction): void
    {
        $auction->load(['bids', 'leftoverPurchases']);
        $allocation = $this->allocate($auction);
        $itemsAllocated = array_sum($allocation['allocations']);
        $leftoverSold = $this->leftoverSoldQuantity($auction);
        $available = max(0, (int) $auction->quantity - $itemsAllocated - $leftoverSold);

        if ($available <= 0) {
            $auction->leftoverPriceOffers()->where('status', 'pending')->update(['status' => 'rejected']);
        }
    }

    /**
     * Load all relations on an auction and return a full bid-included response.
     *
     * @return array<string, mixed>
     */
    public function freshAuctionResponse(Auction $auction): array
    {
        $auction->load([
            'seller:id,username',
            'bids.user:id,username',
            'images',
            'questions.user:id,username',
            'leftoverPurchases.user:id,username',
            'leftoverPriceOffers.user:id,username',
            'category',
            'round',
        ]);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        return $this->auctionResponse($auction, withBids: true);
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
