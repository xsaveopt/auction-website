<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\LeftoverPurchase;
use App\Support\AuctionFinalizationService;
use App\Support\AuctionNotificationService;
use App\Support\AuctionService;
use App\Support\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected AuctionFinalizationService $auctionFinalizationService,
        protected AuctionNotificationService $auctionNotificationService,
    ) {}

    public function index(): JsonResponse
    {
        $this->auctionFinalizationService->finalizeExpiredAuctions();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = Auction::query()
            ->with(['seller:id,username', 'bids', 'images', 'category', 'leftoverPurchases'])
            ->orderByRaw("CASE WHEN status = 'active' AND ends_at > ? THEN 1 ELSE 0 END DESC", [now()])
            ->orderByDesc('created_at')
            ->get();

        $this->auctionService->loadWatcherCounts($auctions);

        return response()->json([
            'auctions' => $auctions->map(fn(Auction $auction) => $this->auctionService->auctionResponse($auction)),
        ]);
    }

    public function show(Auction $auction): JsonResponse
    {
        $this->auctionFinalizationService->finalizeExpiredAuctions();

        $auction->load([
            'seller:id,username',
            'bids.user:id,username',
            'images',
            'questions.user:id,username',
            'leftoverPurchases.user:id,username',
            'leftoverPriceOffers.user:id,username',
            'category',
        ]);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction, withBids: true)]);
    }

    public function ended(): JsonResponse
    {
        $this->auctionFinalizationService->finalizeExpiredAuctions();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $allAuctions */
        $allAuctions = Auction::query()->with([
            'seller:id,username',
            'bids.user:id,username',
            'images',
            'category',
            'leftoverPurchases.user:id,username',
        ])->get();

        $this->auctionService->loadWatcherCounts($allAuctions);

        /** @var array<int, array<string, mixed>> $auctionResponses */
        $auctionResponses = [];
        /** @var float $taxPercentage */
        $taxPercentage = config('auction.invoice.btw_percentage');
        $taxMultiplier = 1 + ($taxPercentage / 100);
        $revenueAfterTax = 0.0;
        $revenueBeforeTax = 0.0;
        $totalValueAfterTax = 0.0;
        $totalValueBeforeTax = 0.0;
        $soldItems = 0;
        $auctionsWithSales = 0;

        foreach ($allAuctions as $auction) {
            $allocation = $this->auctionService->allocate($auction);
            $prices = $allocation['prices'];
            $allocations = $allocation['allocations'];

            $auctionTotalValue = 0.0;
            foreach ($allocations as $bidId => $wonQty) {
                $auctionTotalValue += round($wonQty * ($prices[$bidId] ?? 0.0), 2);
            }
            // For unsold items, use starting price as potential value
            $unsoldItems = $auction->quantity - array_sum($allocations);
            $auctionTotalValue += round($unsoldItems * (float) $auction->starting_price, 2);

            $totalValueAfterTax += $auctionTotalValue;
            $totalValueBeforeTax += round($auctionTotalValue / $taxMultiplier, 2);

            if (!$auction->isActive()) {
                $soldQuantity = array_sum($allocations);
                $soldItems += $soldQuantity;

                $leftoverItemsSold = $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity);
                $soldItems += $leftoverItemsSold;

                if ($soldQuantity > 0 || $leftoverItemsSold > 0) {
                    $auctionsWithSales++;

                    if ($soldQuantity > 0) {
                        $auctionRevenue = 0.0;
                        foreach ($allocations as $bidId => $wonQuantity) {
                            $winnerTotal = round($wonQuantity * ($prices[$bidId] ?? 0.0), 2);
                            $auctionRevenue += $winnerTotal;
                            $revenueBeforeTax += round($winnerTotal / $taxMultiplier, 2);
                        }
                        $revenueAfterTax += $auctionRevenue;
                    }

                    if ($leftoverItemsSold > 0) {
                        $leftoverRevenue = 0.0;
                        foreach ($auction->leftoverPurchases as $purchase) {
                            $leftoverRevenue += round($purchase->quantity * (float) $purchase->price_per_item, 2);
                        }
                        $revenueAfterTax += $leftoverRevenue;
                        $revenueBeforeTax += round($leftoverRevenue / $taxMultiplier, 2);
                    }
                }

                $auctionResponses[] = $this->auctionService->auctionResponseFromAllocation(
                    $auction,
                    $allocation,
                    withBids: true,
                );
            }
        }

        $revenueAfterTax = round($revenueAfterTax, 2);
        $revenueBeforeTax = round($revenueBeforeTax, 2);
        $totalValueAfterTax = round($totalValueAfterTax, 2);
        $totalValueBeforeTax = round($totalValueBeforeTax, 2);

        return response()->json([
            'auctions' => $auctionResponses,
            'summary' => [
                'ended_auctions' => count($auctionResponses),
                'auctions_with_sales' => $auctionsWithSales,
                'sold_items' => $soldItems,
                'revenue_after_tax' => $revenueAfterTax,
                'revenue_before_tax' => $revenueBeforeTax,
                'total_value_after_tax' => $totalValueAfterTax,
                'total_value_before_tax' => $totalValueBeforeTax,
            ],
        ]);
    }

    public function myAuctions(Request $request): JsonResponse
    {
        $this->auctionFinalizationService->finalizeExpiredAuctions();

        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = Auction::query()
            ->with([
                'seller:id,username',
                'bids.user:id,username',
                'images',
                'category',
                'leftoverPurchases.user:id,username',
            ])
            ->whereHas('bids', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        $this->auctionService->loadWatcherCounts($auctions);

        $active = [];
        $won = [];
        $lost = [];

        foreach ($auctions as $auction) {
            $response = $this->auctionService->auctionResponse($auction, withBids: true);
            if ($auction->isActive()) {
                $active[] = $response;
            } else {
                /** @var array<int, array{user: array{id: int, username: string}, won_quantity: int}> $bids */
                $bids = $response['bids'];
                $myBid = collect($bids)->firstWhere('user.id', $user->id);
                if ($myBid && $myBid['won_quantity'] > 0) {
                    $won[] = $response;
                } else {
                    $lost[] = $response;
                }
            }
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $purchasedAuctions */
        $purchasedAuctions = Auction::query()
            ->with([
                'seller:id,username',
                'bids.user:id,username',
                'images',
                'category',
                'leftoverPurchases.user:id,username',
            ])
            ->whereHas('leftoverPurchases', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        $this->auctionService->loadWatcherCounts($purchasedAuctions);

        $purchased = [];
        foreach ($purchasedAuctions as $auction) {
            $purchased[] = $this->auctionService->auctionResponse($auction, withBids: true);
        }

        return response()->json([
            'active' => $active,
            'won' => $won,
            'lost' => $lost,
            'purchased' => $purchased,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{title: string, description: string, location: string|null, starting_price: float, quantity: int, max_per_bidder: int, ends_at: string, category_id?: int|null} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
            'max_per_bidder' => ['required', 'integer', 'min:1'],
            'ends_at' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        if ($validated['max_per_bidder'] > $validated['quantity']) {
            $validated['max_per_bidder'] = $validated['quantity'];
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $auction = $user->auctions()->create($validated);
        $auction->load(['seller:id,username', 'images', 'category']);
        $auction->setAttribute('watcher_count', 0);

        AuditLog::record($user, 'auction.create', $auction, [
            'title' => $auction->title,
            'starting_price' => $auction->starting_price,
            'quantity' => $auction->quantity,
            'ends_at' => $auction->ends_at->toISOString(),
        ]);

        $this->auctionNotificationService->sendNewAuctionNotification($auction);

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction)], 201);
    }

    public function update(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{title: string, description: string, location: string|null, starting_price: float, quantity: int, max_per_bidder: int, ends_at: string, category_id?: int|null} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
            'max_per_bidder' => ['required', 'integer', 'min:1'],
            'ends_at' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        if ($validated['max_per_bidder'] > $validated['quantity']) {
            $validated['max_per_bidder'] = $validated['quantity'];
        }

        $auction->update($validated);
        $auction->load(['seller:id,username', 'bids.user:id,username', 'images', 'category']);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'auction.update', $auction, [
            'title' => $auction->title,
            'starting_price' => $auction->starting_price,
            'quantity' => $auction->quantity,
            'ends_at' => $auction->ends_at->toISOString(),
        ]);

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction, withBids: true)]);
    }

    public function destroy(Request $request, Auction $auction): JsonResponse
    {
        foreach ($auction->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'auction.delete', $auction, ['title' => $auction->title]);

        $auction->delete();

        return response()->json(['message' => 'Auction deleted.']);
    }
}
