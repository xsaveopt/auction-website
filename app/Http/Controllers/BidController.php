<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\SiteSetting;
use App\Support\AuctionNotificationService;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use App\Support\PrometheusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BidController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected AuctionNotificationService $auctionNotificationService,
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

        if (!$auction->isActive()) {
            return response()->json(['message' => 'This auction is no longer active.'], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        Gate::authorize('bid', $auction);

        if (max(1, (int) $auction->max_per_bidder) === 1) {
            $request->merge(['quantity' => 1]);
        }

        $request->validate($this->auctionService->bidRules($auction));

        $amountCents = (int) round($request->float('amount') * 100);
        $quantity = $request->integer('quantity');

        $result = DB::transaction(function () use ($auction, $user, $amountCents, $quantity): JsonResponse|array {
            $this->auctionService->lockForUpdate($auction);

            if (!$auction->isActive()) {
                return response()->json(['message' => 'This auction is no longer active.'], 422);
            }

            $existingBid = Bid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->first();

            if ($existingBid) {
                $existingCents = (int) round((float) $existingBid->amount * 100);
                $existingQuantity = (int) $existingBid->quantity;

                if ($amountCents < $existingCents) {
                    return response()->json(['message' => 'You cannot lower your bid amount.'], 422);
                }

                if ($amountCents === $existingCents && $quantity <= $existingQuantity) {
                    return response()->json([
                        'message' => 'New bid must have a higher amount or a higher quantity than your current bid.',
                    ], 422);
                }

                if ($amountCents > $existingCents && $quantity < $existingQuantity) {
                    return response()->json([
                        'message' => 'You cannot lower your bid quantity, even with a higher amount.',
                    ], 422);
                }
            }

            $auction->loadMissing('bids.user:id,username');
            $previousAllocations = $this->auctionService->allocationByUser($auction);

            $bid = $existingBid ?? new Bid();
            $bid->user_id = $user->id;
            $bid->amount = number_format($amountCents / 100, 2, '.', '');
            $bid->quantity = $quantity;

            if ($existingBid) {
                $bid->save();
            } else {
                $auction->bids()->save($bid);
            }

            $antiSniping = BiddingSchedule::antiSniping();

            if (
                ($bid->wasRecentlyCreated || $bid->wasChanged(['amount', 'quantity']))
                && $antiSniping['enabled']
                && now()->diffInSeconds($auction->ends_at, false) < $antiSniping['window']
            ) {
                $auction->ends_at = $auction->ends_at->addSeconds($antiSniping['extension']);
                $auction->save();
            }

            return ['bid' => $bid, 'existed' => $existingBid !== null, 'previousAllocations' => $previousAllocations];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $bid = $result['bid'];
        $existingBid = $result['existed'];

        app(PrometheusService::class)->recordEvent('bids_placed', ['bidder']);
        $previousAllocations = $result['previousAllocations'];

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
