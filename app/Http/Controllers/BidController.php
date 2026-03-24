<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\SiteSetting;
use App\Support\BiddingSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
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

        if ($auction->seller_id === $user->id) {
            return response()->json(['message' => 'You cannot bid on your own auction.'], 422);
        }

        $maxQty = (int) $auction->max_per_bidder;

        $request->validate([
            'amount' => ['required', 'numeric', 'min:' . $auction->starting_price],
            'quantity' => ['required', 'integer', 'min:1', 'max:' . $maxQty],
        ]);

        /** @var string $amountInput */
        $amountInput = $request->input('amount');
        /** @var string $quantityInput */
        $quantityInput = $request->input('quantity');
        $amount = floatval($amountInput);
        $quantity = intval($quantityInput);

        $existingBid = Bid::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if ($existingBid) {
            if ($amount < floatval($existingBid->amount)) {
                return response()->json(['message' => 'You cannot lower your bid amount.'], 422);
            }
            if ($amount <= floatval($existingBid->amount) && $quantity === $existingBid->quantity) {
                return response()->json([
                    'message' => 'New bid must be higher than your current bid ($' . $existingBid->amount . ').',
                ], 422);
            }
        }

        $bid = new Bid();
        $bid->user_id = $user->id;
        $bid->amount = number_format($amount, 2, '.', '');
        $bid->quantity = $quantity;
        $auction->bids()->save($bid);

        // Anti-sniping: extend auction if bid placed near the end
        $antiSnipingEnabled = boolval(config('auction.anti_sniping_enabled', true));
        /** @var int $antiSnipingWindow */
        $antiSnipingWindow = config('auction.anti_sniping_window', 60);
        /** @var int $antiSnipingExtension */
        $antiSnipingExtension = config('auction.anti_sniping_extension', 300);

        if ($antiSnipingEnabled && now()->diffInSeconds($auction->ends_at, false) < $antiSnipingWindow) {
            $auction->ends_at = $auction->ends_at->addSeconds($antiSnipingExtension);
            $auction->save();
        }

        $bid->load('user:id,username');

        /** @var \App\Models\User $bidUser */
        $bidUser = $bid->user;

        return response()->json([
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
        ], 201);
    }
}
