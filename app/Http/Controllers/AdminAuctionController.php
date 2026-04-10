<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionRound;
use App\Models\AuditLog;
use App\Models\Category;
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

    public function bulkUpdate(Request $request): JsonResponse
    {
        /** @var array{action: string, auction_ids: list<int>, round_id?: int|null, category_id?: int|null, location?: string|null} $validated */
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:assign_round,assign_category,assign_location,end,cancel'],
            'auction_ids' => ['required', 'array', 'min:1'],
            'auction_ids.*' => ['integer', 'exists:auctions,id'],
            'round_id' => ['nullable', 'integer', 'exists:auction_rounds,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $admin */
        $admin = $request->user();
        $ids = $validated['auction_ids'];

        switch ($validated['action']) {
            case 'assign_round':
                $roundId = $validated['round_id'] ?? null;
                Auction::whereIn('id', $ids)->update(['auction_round_id' => $roundId]);
                AuditLog::record($admin, 'auction.bulk_assign_round', null, [
                    'auction_ids' => $ids,
                    'round_id' => $roundId,
                ]);
                break;

            case 'assign_category':
                $categoryId = $validated['category_id'] ?? null;
                Auction::whereIn('id', $ids)->update(['category_id' => $categoryId]);
                AuditLog::record($admin, 'auction.bulk_assign_category', null, [
                    'auction_ids' => $ids,
                    'category_id' => $categoryId,
                ]);
                break;

            case 'assign_location':
                $location = $validated['location'] ?? null;
                Auction::whereIn('id', $ids)->update(['location' => $location]);
                AuditLog::record($admin, 'auction.bulk_assign_location', null, [
                    'auction_ids' => $ids,
                    'location' => $location,
                ]);
                break;

            case 'end':
                $auctions = Auction::whereIn('id', $ids)
                    ->where('status', 'active')
                    ->with('bids.user:id,username')
                    ->get();
                foreach ($auctions as $auction) {
                    $this->auctionFinalizationService->end($auction);
                }
                AuditLog::record($admin, 'auction.bulk_end', null, [
                    'auction_ids' => $ids,
                    'ended_count' => $auctions->count(),
                ]);
                break;

            case 'cancel':
                $auctions = Auction::whereIn('id', $ids)
                    ->where('status', 'active')
                    ->with('bids.user:id,username')
                    ->get();
                foreach ($auctions as $auction) {
                    $this->auctionFinalizationService->cancel($auction);
                }
                AuditLog::record($admin, 'auction.bulk_cancel', null, [
                    'auction_ids' => $ids,
                    'cancelled_count' => $auctions->count(),
                ]);
                break;
        }

        return response()->json(['updated' => count($ids)]);
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
