<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\BiddingSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function store(Request $request, Auction $auction): JsonResponse
    {
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

        $bid = Bid::where('auction_id', $auction->id)->where('user_id', $user->id)->first();

        /** @var string $amountInput */
        $amountInput = $request->input('amount');
        /** @var string $quantityInput */
        $quantityInput = $request->input('quantity');
        $amount = floatval($amountInput);
        $quantity = intval($quantityInput);

        if ($bid) {
            if ($amount <= floatval($bid->amount) && $quantity === $bid->quantity) {
                return response()->json([
                    'message' => 'New bid must be higher than your current bid ($' . $bid->amount . ').',
                ], 422);
            }
            if ($amount < floatval($bid->amount)) {
                return response()->json(['message' => 'You cannot lower your bid amount.'], 422);
            }
            $bid->amount = number_format($amount, 2, '.', '');
            $bid->quantity = $quantity;
            $bid->save();
        } else {
            $bid = new Bid();
            $bid->user_id = $user->id;
            $bid->amount = number_format($amount, 2, '.', '');
            $bid->quantity = $quantity;
            $auction->bids()->save($bid);
        }

        // Soft Close: extend by 2 minutes if bid is within 2 minutes of end
        if (now()->diffInSeconds($auction->ends_at, false) < 120) {
            $auction->ends_at = $auction->ends_at->addMinutes(2);
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
