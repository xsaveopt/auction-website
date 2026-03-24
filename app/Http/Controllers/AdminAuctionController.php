<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Support\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function end(Auction $auction): JsonResponse
    {
        $auction->status = 'ended';
        $auction->ends_at = now();
        $auction->save();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function cancel(Auction $auction): JsonResponse
    {
        $auction->status = 'cancelled';
        $auction->ends_at = now();
        $auction->save();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function reactivate(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{ends_at: string} $validated */
        $validated = $request->validate([
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $auction->status = 'active';
        $auction->ends_at = Carbon::parse($validated['ends_at']);
        $auction->save();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function extend(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{ends_at: string} $validated */
        $validated = $request->validate([
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $auction->ends_at = Carbon::parse($validated['ends_at']);
        $auction->save();

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
