<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Support\Presence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = $this
            ->auctionQuery()
            ->with(['seller:id,username', 'bids', 'images'])
            ->orderByDesc('watcher_count')
            ->orderByRaw("CASE WHEN status = 'active' AND ends_at > ? THEN 1 ELSE 0 END DESC", [now()])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'auctions' => $auctions->map(fn(Auction $auction) => $this->auctionResponse($auction)),
        ]);
    }

    public function show(Auction $auction): JsonResponse
    {
        /** @var Auction $auction */
        $auction = $this
            ->auctionQuery()
            ->with(['seller:id,username', 'bids.user:id,username', 'images', 'questions.user:id,username'])
            ->findOrFail($auction->id);

        return response()->json(['auction' => $this->auctionResponse($auction, withBids: true)]);
    }

    public function ended(): JsonResponse
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = $this
            ->auctionQuery()
            ->with(['seller:id,username', 'bids.user:id,username', 'images'])
            ->where(function ($q) {
                $q->where('ends_at', '<=', now())->orWhere('status', '!=', 'active');
            })
            ->orderByDesc('watcher_count')
            ->orderByDesc('ends_at')
            ->get();

        return response()->json([
            'auctions' => $auctions->map(fn(Auction $auction) => $this->auctionResponse($auction, withBids: true)),
        ]);
    }

    public function myAuctions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
        $auctions = $this
            ->auctionQuery()
            ->with(['seller:id,username', 'bids.user:id,username', 'images'])
            ->whereHas('bids', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        $active = [];
        $won = [];
        $lost = [];

        foreach ($auctions as $auction) {
            $response = $this->auctionResponse($auction, withBids: true);
            if ($auction->isActive()) {
                $active[] = $response;
            } else {
                $myBid = collect($response['bids'])->firstWhere('user.id', $user->id);
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

        return response()->json(['auction' => $this->auctionResponse($auction)], 201);
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

        return response()->json(['auction' => $this->auctionResponse($auction, withBids: true)]);
    }

    public function destroy(Auction $auction): JsonResponse
    {
        foreach ($auction->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $auction->delete();

        return response()->json(['message' => 'Auction deleted.']);
    }

    /**
     * Allocate items to bids, sorted by amount descending.
     * Returns a map of bid ID => number of items won.
     *
     * @return array{allocations: array<int, int>, clearing_price: float}
     */
    private function allocate(Auction $auction): array
    {
        $sortedBids = $auction->bids->sortByDesc('amount')->values();
        $remaining = (int) $auction->quantity;
        /** @var array<int, int> $allocations */
        $allocations = [];
        $clearingPrice = (float) $auction->starting_price;

        foreach ($sortedBids as $bid) {
            if ($remaining <= 0) {
                break;
            }

            $give = min((int) $bid->quantity, $remaining);
            $allocations[$bid->id] = $give;
            $remaining -= $give;
            $clearingPrice = (float) $bid->amount;
        }

        return [
            'allocations' => $allocations,
            'clearing_price' => $clearingPrice,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auctionResponse(Auction $auction, bool $withBids = false): array
    {
        $result = $this->allocate($auction);
        $allocations = $result['allocations'];
        $clearingPrice = $result['clearing_price'];

        $data = [
            'id' => $auction->id,
            'title' => $auction->title,
            'description' => $auction->description,
            'starting_price' => $auction->starting_price,
            'current_price' => $clearingPrice,
            'quantity' => $auction->quantity,
            'max_per_bidder' => $auction->max_per_bidder,
            'ends_at' => $auction->ends_at->toISOString(),
            'status' => $auction->status,
            'is_active' => $auction->isActive(),
            'seller' => $auction->seller
                ? [
                    'id' => $auction->seller->id,
                    'username' => $auction->seller->username,
                ] : null,
            'bid_count' => $auction->bids->count(),
            'watcher_count' => $auction->watcher_count,
            'items_allocated' => array_sum($allocations),
            'images' => $auction
                ->images
                ->map(fn($img) => [
                    'id' => $img->id,
                    'url' => "/api/images/{$img->id}",
                ])
                ->values(),
            'created_at' => $auction->created_at?->toISOString(),
        ];

        if ($withBids) {
            $sortedBids = $auction->bids->sortByDesc('amount')->values();
            $data['bids'] = $sortedBids->map(fn(\App\Models\Bid $bid) => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'quantity' => $bid->quantity,
                'won_quantity' => $allocations[$bid->id] ?? 0,
                'user' => [
                    'id' => $bid->user?->id,
                    'username' => $bid->user?->username,
                ],
                'created_at' => $bid->created_at?->toISOString(),
            ]);
        }

        if ($auction->relationLoaded('questions')) {
            $data['questions'] = $auction
                ->questions
                ->map(fn(\App\Models\AuctionQuestion $question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'answer' => $question->answer,
                    'answered_at' => $question->answered_at?->toISOString(),
                    'user' => [
                        'id' => $question->user?->id,
                        'username' => $question->user?->username,
                    ],
                    'created_at' => $question->created_at?->toISOString(),
                ])
                ->values();
        }

        return $data;
    }

    /** @return Builder<Auction> */
    private function auctionQuery(): Builder
    {
        return Auction::query()
            ->select('auctions.*')
            ->addSelect([
                'watcher_count' => Presence::watcherCountSubquery(),
            ]);
    }
}
