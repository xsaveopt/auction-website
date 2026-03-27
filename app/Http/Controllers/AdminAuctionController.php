<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Support\AuctionFinalizationService;
use App\Support\AuctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected AuctionFinalizationService $auctionFinalizationService,
    ) {}

    public function end(Auction $auction): JsonResponse
    {
        $this->auctionFinalizationService->end($auction);

        /** @var \App\Models\User $admin */
        $admin = auth()->user();
        AuditLog::record($admin, 'auction.end', $auction, ['title' => $auction->title]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function cancel(Auction $auction): JsonResponse
    {
        $this->auctionFinalizationService->cancel($auction);

        /** @var \App\Models\User $admin */
        $admin = auth()->user();
        AuditLog::record($admin, 'auction.cancel', $auction, ['title' => $auction->title]);

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
        $auction->ending_soon_notified = false;
        $auction->save();

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'auction.reactivate', $auction, [
            'title' => $auction->title,
            'ends_at' => $auction->ends_at->toISOString(),
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }

    public function extend(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{ends_at: string} $validated */
        $validated = $request->validate([
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $auction->ends_at = Carbon::parse($validated['ends_at']);
        $auction->ending_soon_notified = false;
        $auction->save();

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'auction.extend', $auction, [
            'title' => $auction->title,
            'ends_at' => $auction->ends_at->toISOString(),
        ]);

        return response()->json(['auction' => $this->auctionService->freshAuctionResponse($auction)]);
    }
}
