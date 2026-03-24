<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
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

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'bid.create', $bid, [
            'auction_id' => $auction->id,
            'auction_title' => $auction->title,
            'bidder' => $user->username,
            'amount' => $bid->amount,
            'quantity' => $bid->quantity,
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function update(Request $request, Bid $bid): JsonResponse
    {
        /** @var array{amount: string, quantity: int} $validated */
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $bid->load('user:id,username', 'auction');
        $oldAmount = $bid->amount;
        $oldQuantity = $bid->quantity;

        $bid->amount = $validated['amount'];
        $bid->quantity = $validated['quantity'];
        $bid->save();

        /** @var Auction $auction */
        $auction = $bid->auction;

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'bid.update', $bid, [
            'auction_id' => $bid->auction_id,
            'auction_title' => $auction->title,
            'bidder' => $bid->user?->username,
            'old_amount' => $oldAmount,
            'old_quantity' => $oldQuantity,
            'new_amount' => $bid->amount,
            'new_quantity' => $bid->quantity,
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function destroy(Bid $bid): JsonResponse
    {
        $auctionId = $bid->auction_id;
        $bid->load('user:id,username', 'auction:id,title');

        /** @var \App\Models\User $admin */
        $admin = auth()->user();
        AuditLog::record($admin, 'bid.delete', $bid, [
            'auction_id' => $bid->auction_id,
            'auction_title' => $bid->auction?->title,
            'bidder' => $bid->user?->username,
            'amount' => $bid->amount,
            'quantity' => $bid->quantity,
        ]);

        $bid->delete();

        /** @var Auction $auction */
        $auction = Auction::findOrFail($auctionId);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
