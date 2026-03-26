<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\LeftoverPriceOffer;
use App\Models\LeftoverPurchase;
use App\Support\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeftoverPriceOfferController extends Controller
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
            return response()->json(['message' => 'You cannot make an offer on your own auction.'], 403);
        }

        if ($auction->leftoverPurchases()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already purchased leftover items from this auction.'], 422);
        }

        if ($auction->leftoverPriceOffers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already submitted a price offer for this auction.'], 422);
        }

        $auction->load(['bids', 'leftoverPurchases']);
        $allocation = $this->auctionService->allocate($auction);
        $itemsAllocated = array_sum($allocation['allocations']);
        $leftoverSold = $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity);
        $available = (int) $auction->quantity - $itemsAllocated - $leftoverSold;

        if ($available <= 0) {
            return response()->json(['message' => 'No leftover items are available.'], 422);
        }

        /** @var float $leftoverPriceFactor */
        $leftoverPriceFactor = config('auction.leftover_price_factor', 0.75);
        $leftoverPrice = round((float) $auction->starting_price * $leftoverPriceFactor, 2);

        /** @var array{quantity: int, offered_price_per_item: float} $validated */
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', "max:{$available}"],
            'offered_price_per_item' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . number_format($leftoverPrice - 0.01, 2, '.', ''),
            ],
        ]);

        $offer = $auction
            ->leftoverPriceOffers()
            ->create([
                'user_id' => $user->id,
                'quantity' => $validated['quantity'],
                'offered_price_per_item' => $validated['offered_price_per_item'],
                'status' => 'pending',
            ]);

        $auction->load([
            'seller:id,username',
            'bids.user:id,username',
            'images',
            'questions.user:id,username',
            'leftoverPurchases.user:id,username',
            'leftoverPriceOffers.user:id,username',
            'category',
        ]);

        return response()->json([
            'auction' => $this->auctionService->auctionResponse($auction, withBids: true),
        ], 201);
    }

    public function accept(Request $request, LeftoverPriceOffer $leftoverPriceOffer): JsonResponse
    {
        if ($leftoverPriceOffer->status !== 'pending') {
            return response()->json(['message' => 'This offer is no longer pending.'], 422);
        }

        /** @var \App\Models\Auction $auction */
        $auction = $leftoverPriceOffer->auction()->with(['bids', 'leftoverPurchases'])->firstOrFail();

        $allocation = $this->auctionService->allocate($auction);
        $itemsAllocated = array_sum($allocation['allocations']);
        $leftoverSold = $auction->leftoverPurchases->sum(fn(LeftoverPurchase $p) => $p->quantity);
        $available = (int) $auction->quantity - $itemsAllocated - $leftoverSold;

        if ($available < $leftoverPriceOffer->quantity) {
            return response()->json([
                'message' => "Only {$available} item(s) available; cannot fulfil this offer.",
            ], 422);
        }

        if ($auction->leftoverPurchases()->where('user_id', $leftoverPriceOffer->user_id)->exists()) {
            return response()->json(['message' => 'This user already has a purchase for this auction.'], 422);
        }

        $purchase = $auction
            ->leftoverPurchases()
            ->create([
                'user_id' => $leftoverPriceOffer->user_id,
                'quantity' => $leftoverPriceOffer->quantity,
                'price_per_item' => $leftoverPriceOffer->offered_price_per_item,
            ]);

        $leftoverPriceOffer->update(['status' => 'accepted']);

        $this->auctionService->closePendingOffersIfSoldOut($auction);

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'leftover_price_offer.accept', $purchase, [
            'auction_id' => $auction->id,
            'auction_title' => $auction->title,
            'buyer' => $leftoverPriceOffer->user?->username,
            'quantity' => $leftoverPriceOffer->quantity,
            'offered_price_per_item' => $leftoverPriceOffer->offered_price_per_item,
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function reject(Request $request, LeftoverPriceOffer $leftoverPriceOffer): JsonResponse
    {
        if ($leftoverPriceOffer->status !== 'pending') {
            return response()->json(['message' => 'This offer is no longer pending.'], 422);
        }

        $leftoverPriceOffer->update(['status' => 'rejected']);

        /** @var \App\Models\Auction $auction */
        $auction = $leftoverPriceOffer->auction()->firstOrFail();

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'leftover_price_offer.reject', $leftoverPriceOffer, [
            'auction_id' => $auction->id,
            'auction_title' => $auction->title,
            'buyer' => $leftoverPriceOffer->user?->username,
            'quantity' => $leftoverPriceOffer->quantity,
            'offered_price_per_item' => $leftoverPriceOffer->offered_price_per_item,
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
