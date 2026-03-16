<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Support\AuctionService;
use App\Support\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function index(): JsonResponse
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = Auction::query()
            ->with(['seller:id,username', 'bids', 'images'])
            ->orderByRaw("CASE WHEN status = 'active' AND ends_at > ? THEN 1 ELSE 0 END DESC", [now()])
            ->orderByDesc('created_at')
            ->get();

        $this->auctionService->loadWatcherCounts($auctions);
        $auctions = $auctions->sortByDesc('watcher_count')->values();

        return response()->json([
            'auctions' => $auctions->map(fn(Auction $auction) => $this->auctionService->auctionResponse($auction)),
        ]);
    }

    public function show(Auction $auction): JsonResponse
    {
        $auction->load(['seller:id,username', 'bids.user:id,username', 'images', 'questions.user:id,username']);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction, withBids: true)]);
    }

    public function ended(): JsonResponse
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $allAuctions */
        $allAuctions = Auction::query()->with(['seller:id,username', 'bids.user:id,username', 'images'])->get();

        $this->auctionService->loadWatcherCounts($allAuctions);

        /** @var array<int, array<string, mixed>> $auctionResponses */
        $auctionResponses = [];
        /** @var float $taxPercentage */
        $taxPercentage = config('auction.invoice.btw_percentage');
        $taxMultiplier = 1 + ($taxPercentage / 100);
        $revenueAfterTax = 0.0;
        $revenueBeforeTax = 0.0;
        $totalValueAfterTax = 0.0;
        $totalValueBeforeTax = 0.0;
        $soldItems = 0;
        $auctionsWithSales = 0;

        foreach ($allAuctions as $auction) {
            $allocation = $this->auctionService->allocate($auction);
            $auctionTotalValue = round($auction->quantity * $allocation['clearing_price'], 2);

            $totalValueAfterTax += $auctionTotalValue;
            $totalValueBeforeTax += round($auctionTotalValue / $taxMultiplier, 2);

            if (!$auction->isActive()) {
                $soldQuantity = array_sum($allocation['allocations']);
                $soldItems += $soldQuantity;

                if ($soldQuantity > 0) {
                    $auctionsWithSales++;
                    $auctionRevenue = round($soldQuantity * $allocation['clearing_price'], 2);
                    $revenueAfterTax += $auctionRevenue;

                    foreach ($allocation['allocations'] as $wonQuantity) {
                        $winnerTotal = round($wonQuantity * $allocation['clearing_price'], 2);
                        $revenueBeforeTax += round($winnerTotal / $taxMultiplier, 2);
                    }
                }

                $auctionResponses[] = $this->auctionService->auctionResponseFromAllocation(
                    $auction,
                    $allocation,
                    withBids: true,
                );
            }
        }

        $revenueAfterTax = round($revenueAfterTax, 2);
        $revenueBeforeTax = round($revenueBeforeTax, 2);
        $totalValueAfterTax = round($totalValueAfterTax, 2);
        $totalValueBeforeTax = round($totalValueBeforeTax, 2);

        return response()->json([
            'auctions' => $auctionResponses,
            'summary' => [
                'ended_auctions' => count($auctionResponses),
                'auctions_with_sales' => $auctionsWithSales,
                'sold_items' => $soldItems,
                'revenue_after_tax' => $revenueAfterTax,
                'revenue_before_tax' => $revenueBeforeTax,
                'total_value_after_tax' => $totalValueAfterTax,
                'total_value_before_tax' => $totalValueBeforeTax,
            ],
        ]);
    }

    public function myAuctions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = Auction::query()
            ->with(['seller:id,username', 'bids.user:id,username', 'images'])
            ->whereHas('bids', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        $this->auctionService->loadWatcherCounts($auctions);

        $active = [];
        $won = [];
        $lost = [];

        foreach ($auctions as $auction) {
            $response = $this->auctionService->auctionResponse($auction, withBids: true);
            if ($auction->isActive()) {
                $active[] = $response;
            } else {
                /** @var array<int, array{user: array{id: int, username: string}, won_quantity: int}> $bids */
                $bids = $response['bids'];
                $myBid = collect($bids)->firstWhere('user.id', $user->id);
                if ($myBid && $myBid['won_quantity'] > 0) {
                    $won[] = $response;
                } else {
                    $lost[] = $response;
                }
            }
        }

        return response()->json([
            'active' => $active,
            'won' => $won,
            'lost' => $lost,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{title: string, description: string, starting_price: float, quantity: int, max_per_bidder: int, ends_at: string} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
            'max_per_bidder' => ['required', 'integer', 'min:1'],
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        if ($validated['max_per_bidder'] > $validated['quantity']) {
            $validated['max_per_bidder'] = $validated['quantity'];
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $auction = $user->auctions()->create($validated);
        $auction->load(['seller:id,username', 'images']);
        $auction->setAttribute('watcher_count', 0);

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction)], 201);
    }

    public function update(Request $request, Auction $auction): JsonResponse
    {
        /** @var array{title: string, description: string, starting_price: float, quantity: int, max_per_bidder: int, ends_at: string} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'quantity' => ['required', 'integer', 'min:1'],
            'max_per_bidder' => ['required', 'integer', 'min:1'],
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        if ($validated['max_per_bidder'] > $validated['quantity']) {
            $validated['max_per_bidder'] = $validated['quantity'];
        }

        $auction->update($validated);
        $auction->load(['seller:id,username', 'bids.user:id,username', 'images']);
        $auction->setAttribute('watcher_count', Presence::watchersForAuction($auction->id));

        return response()->json(['auction' => $this->auctionService->auctionResponse($auction, withBids: true)]);
    }

    public function destroy(Auction $auction): JsonResponse
    {
        foreach ($auction->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $auction->delete();

        return response()->json(['message' => 'Auction deleted.']);
    }
}
