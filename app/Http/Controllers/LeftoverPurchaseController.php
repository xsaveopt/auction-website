<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\LeftoverPurchase;
use App\Models\User;
use App\Support\AuctionService;
use App\Support\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeftoverPurchaseController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function store(Request $request, Auction $auction): JsonResponse
    {
        if (!(bool) config('auction.leftover_sales_enabled')) {
            return response()->json(['message' => 'Leftover sales are not enabled.'], 403);
        }

        if ($auction->isActive()) {
            return response()->json(['message' => 'This auction is still active.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->id === $auction->seller_id) {
            return response()->json(['message' => 'You cannot purchase from your own auction.'], 403);
        }

        if ($auction->leftoverPurchases()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already purchased leftover items from this auction.'], 422);
        }

        $auction->load(['bids', 'leftoverPurchases']);
        $allocation = $this->auctionService->allocate($auction);
        $itemsAllocated = array_sum($allocation['allocations']);
        $leftoverSold = $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity);
        $available = (int) $auction->quantity - $itemsAllocated - $leftoverSold;

        if ($available <= 0) {
            return response()->json(['message' => 'No leftover items are available.'], 422);
        }

        /** @var array{quantity: int} $validated */
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', "max:{$available}"],
        ]);

        /** @var float $leftoverPriceFactor */
        $leftoverPriceFactor = config('auction.leftover_price_factor', 0.75);
        $pricePerItem = round((float) $auction->starting_price * $leftoverPriceFactor, 2);

        $auction
            ->leftoverPurchases()
            ->create([
                'user_id' => $user->id,
                'quantity' => $validated['quantity'],
                'price_per_item' => $pricePerItem,
            ]);

        $auction->load([
            'seller:id,username',
            'bids.user:id,username',
            'images',
            'questions.user:id,username',
            'leftoverPurchases.user:id,username',
            'category',
        ]);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        return response()->json([
            'auction' => $this->auctionService->auctionResponse($auction, withBids: true),
        ], 201);
    }

    public function adminStore(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{username: string, quantity: int} $validated */
        $validated = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $buyer */
        $buyer = User::query()->where('username', $validated['username'])->firstOrFail();

        $auction->load(['bids', 'leftoverPurchases']);
        $allocation = $this->auctionService->allocate($auction);
        $itemsAllocated = array_sum($allocation['allocations']);
        $leftoverSold = $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity);
        $available = (int) $auction->quantity - $itemsAllocated - $leftoverSold;

        if ($available <= 0) {
            return response()->json(['message' => 'No leftover items are available.'], 422);
        }

        if ($validated['quantity'] > $available) {
            return response()->json(['message' => "Only {$available} item(s) available."], 422);
        }

        /** @var float $leftoverPriceFactor */
        $leftoverPriceFactor = config('auction.leftover_price_factor', 0.75);
        $pricePerItem = round((float) $auction->starting_price * $leftoverPriceFactor, 2);

        $existing = $auction->leftoverPurchases()->where('user_id', $buyer->id)->first();

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + $validated['quantity']]);
        } else {
            $auction
                ->leftoverPurchases()
                ->create([
                    'user_id' => $buyer->id,
                    'quantity' => $validated['quantity'],
                    'price_per_item' => $pricePerItem,
                ]);
        }

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)], 201);
    }

    public function destroy(LeftoverPurchase $leftoverPurchase): JsonResponse
    {
        /** @var \App\Models\Auction $auction */
        $auction = $leftoverPurchase->auction()->firstOrFail();
        $leftoverPurchase->delete();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
