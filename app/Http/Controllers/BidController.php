<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\SiteSetting;
use App\Support\AuctionFinalizationService;
use App\Support\AuctionNotificationService;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected AuctionNotificationService $auctionNotificationService,
        protected AuctionFinalizationService $auctionFinalizationService,
    ) {}

    public function store(Request $request, Auction $auction): JsonResponse
    {
        if (SiteSetting::isLocked()) {
            return response()->json(['message' => 'The site is temporarily closed for maintenance.'], 422);
        }

        if (!BiddingSchedule::isBiddingOpen()) {
            return response()->json([
                'message' =>
                    'Bidding is closed during office hours ('
                        . BiddingSchedule::closedStart()
                        . ' – '
                        . BiddingSchedule::closedEnd()
                        . ').',
            ], 422);
        }

        $this->auctionFinalizationService->finalizeExpiredAuctions();

        if (!$auction->isActive()) {
            return response()->json(['message' => 'This auction is no longer active.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($auction->seller_id === $user->id) {
            return response()->json(['message' => 'You cannot bid on your own auction.'], 422);
        }

        $maxQty = max(1, (int) $auction->max_per_bidder);

        if ($maxQty === 1) {
            $request->merge(['quantity' => 1]);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'min:' . $auction->starting_price],
            'quantity' => ['required', 'integer', 'min:1', 'max:' . $maxQty],
        ]);

        $amount = $request->float('amount');
        $quantity = $request->integer('quantity');

        $existingBid = Bid::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if ($existingBid) {
            $existingAmount = floatval($existingBid->amount);
            $existingQuantity = (int) $existingBid->quantity;

            if ($amount < $existingAmount) {
                return response()->json(['message' => 'You cannot lower your bid amount.'], 422);
            }

            if ($amount === $existingAmount && $quantity <= $existingQuantity) {
                return response()->json([
                    'message' => 'New bid must have a higher amount or a higher quantity than your current bid.',
                ], 422);
            }

            if ($amount > $existingAmount && $quantity < $existingQuantity) {
                return response()->json([
                    'message' => 'You cannot lower your bid quantity, even with a higher amount.',
                ], 422);
            }
        }

        $auction->loadMissing('bids.user:id,username');
        $previousAllocations = $this->auctionService->allocationByUser($auction);

        $bid = $existingBid ?? new Bid();
        $bid->user_id = $user->id;
        $bid->amount = number_format($amount, 2, '.', '');
        $bid->quantity = $quantity;

        if ($existingBid) {
            $bid->save();
        } else {
            $auction->bids()->save($bid);
        }

        // Anti-sniping: extend auction if bid placed near the end
        $antiSniping = BiddingSchedule::antiSniping();

        if ($antiSniping['enabled'] && now()->diffInSeconds($auction->ends_at, false) < $antiSniping['window']) {
            $auction->ends_at = $auction->ends_at->addSeconds($antiSniping['extension']);
            $auction->save();
        }

        $auction->unsetRelation('bids');
        $auction->load('bids.user:id,username');
        $currentAllocations = $this->auctionService->allocationByUser($auction);
        $this->auctionNotificationService->sendOverbidNotifications(
            $auction,
            $previousAllocations,
            $currentAllocations,
            $user->id,
        );

        $bid->load('user:id,username');

        /** @var \App\Models\User $bidUser */
        $bidUser = $bid->user;

        return response()->json(
            [
                'bid' => [
                    'id' => $bid->id,
                    'amount' => $bid->amount,
                    'quantity' => $bid->quantity,
                    'user' => [
                        'id' => $bidUser->id,
                        'username' => $bidUser->username,
                    ],
                    'created_at' => $bid->created_at?->toISOString(),
                ],
            ],
            $existingBid ? 200 : 201,
        );
    }
}
