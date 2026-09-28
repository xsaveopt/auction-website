<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\LeftoverPurchase;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AuctionService;
use App\Support\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeftoverPurchaseController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function store(Request $request, Auction $auction): JsonResponse
    {
        if (!SiteSetting::instance()->leftover_sales_enabled) {
            return response()->json(['message' => 'Leftover sales are not enabled.'], 403);
        }

        if ($auction->isActive()) {
            return response()->json(['message' => 'This auction is still active.'], 422);
        }

        $auction->loadMissing('round');
        if ($auction->round && $auction->round->status === 'ended') {
            return response()->json(['message' => 'This auction\'s round has been closed.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        Gate::authorize('purchaseLeftover', $auction);

        $soldOut = DB::transaction(function () use ($request, $auction, $user): ?JsonResponse {
            $this->auctionService->lockForUpdate($auction);

            $auction->load(['bids', 'leftoverPurchases']);
            $available = $this->auctionService->availableLeftoverQuantity($auction);

            if ($available <= 0) {
                return response()->json(['message' => 'No leftover items are available.'], 422);
            }

            /** @var array{quantity: int} $validated */
            $validated = $request->validate([
                'quantity' => ['required', 'integer', 'min:1', "max:{$available}"],
            ]);

            $pricePerItem = $this->auctionService->leftoverPrice($auction);

            $existing = $auction
                ->leftoverPurchases()
                ->where('user_id', $user->id)
                ->where('is_override', false)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $validated['quantity']]);
            } else {
                $auction
                    ->leftoverPurchases()
                    ->create([
                        'user_id' => $user->id,
                        'quantity' => $validated['quantity'],
                        'price_per_item' => $pricePerItem,
                    ]);
            }

            $this->auctionService->closePendingOffersIfSoldOut($auction);

            return null;
        });

        if ($soldOut !== null) {
            return $soldOut;
        }

        app(PrometheusService::class)->recordEvent('leftover_items_sold', ['buy'], $request->integer('quantity'));

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)], 201);
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

        $result = DB::transaction(function () use ($auction, $buyer, $validated): JsonResponse|array {
            $this->auctionService->lockForUpdate($auction);

            $auction->load(['bids', 'leftoverPurchases']);
            $available = $this->auctionService->availableLeftoverQuantity($auction);

            if ($available <= 0) {
                return response()->json(['message' => 'No leftover items are available.'], 422);
            }

            if ($validated['quantity'] > $available) {
                return response()->json(['message' => "Only {$available} item(s) available."], 422);
            }

            $pricePerItem = $this->auctionService->leftoverPrice($auction);

            $existing = $auction
                ->leftoverPurchases()
                ->where('user_id', $buyer->id)
                ->where('is_override', false)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $validated['quantity']]);
                $purchase = $existing;
            } else {
                $purchase = $auction
                    ->leftoverPurchases()
                    ->create([
                        'user_id' => $buyer->id,
                        'quantity' => $validated['quantity'],
                        'price_per_item' => $pricePerItem,
                    ]);
            }

            $this->auctionService->closePendingOffersIfSoldOut($auction);

            return ['purchase' => $purchase, 'price_per_item' => $pricePerItem];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $purchase = $result['purchase'];
        $pricePerItem = $result['price_per_item'];

        app(PrometheusService::class)->recordEvent('leftover_items_sold', ['admin'], $validated['quantity']);

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'leftover_purchase.create', $purchase, [
            'auction_id' => $auction->id,
            'auction_title' => $auction->title,
            'buyer' => $buyer->username,
            'quantity' => $validated['quantity'],
            'price_per_item' => $pricePerItem,
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)], 201);
    }

    public function destroy(LeftoverPurchase $leftoverPurchase): JsonResponse
    {
        $leftoverPurchase->load('user:id,username', 'auction:id,title');
        /** @var \App\Models\Auction $auction */
        $auction = $leftoverPurchase->auction()->firstOrFail();

        /** @var \App\Models\User $admin */
        $admin = auth()->user();
        AuditLog::record($admin, 'leftover_purchase.delete', $leftoverPurchase, [
            'auction_id' => $leftoverPurchase->auction_id,
            'auction_title' => $leftoverPurchase->auction?->title,
            'buyer' => $leftoverPurchase->user?->username,
            'quantity' => $leftoverPurchase->quantity,
            'price_per_item' => $leftoverPurchase->price_per_item,
        ]);

        $leftoverPurchase->delete();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
