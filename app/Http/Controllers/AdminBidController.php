<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Support\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBidController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function users(): JsonResponse
    {
        $users = User::where('is_admin', false)->orderBy('username')->get(['id', 'username']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{username: string, amount: string, quantity: int} $validated */
        $validated = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = User::where('username', $validated['username'])->firstOrFail();

        $bid = new Bid();
        $bid->auction_id = $auction->id;
        $bid->user_id = $user->id;
        $bid->amount = $validated['amount'];
        $bid->quantity = $validated['quantity'];
        $bid->save();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function update(Request $request, Bid $bid): JsonResponse
    {
        /** @var array{amount: string, quantity: int} $validated */
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bid->amount = $validated['amount'];
        $bid->quantity = $validated['quantity'];
        $bid->save();

        $bid->load('auction');

        /** @var Auction $auction */
        $auction = $bid->auction;

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function destroy(Bid $bid): JsonResponse
    {
        $auctionId = $bid->auction_id;
        $bid->delete();

        /** @var Auction $auction */
        $auction = Auction::findOrFail($auctionId);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
