<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Support\AuctionService;
use App\Support\Presence;
use App\Support\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PresenceController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
        protected StatsService $statsService,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var array{page_id: string, client_id: string, page_type: string, auction_id?: int} $validated */
        $validated = $request->validate([
            'page_id' => ['required', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:100'],
            'page_type' => ['required', 'string', Rule::in(['page', 'home', 'auction'])],
            'auction_id' => ['nullable', 'integer', 'exists:auctions,id'],
        ]);

        if ($validated['page_type'] === 'auction' && !array_key_exists('auction_id', $validated)) {
            throw ValidationException::withMessages([
                'auction_id' => 'The auction_id field is required when viewing an auction.',
            ]);
        }

        $auctionId = $validated['page_type'] === 'auction' && isset($validated['auction_id'])
            ? $validated['auction_id']
            : null;

        Presence::heartbeat(
            pageId: $validated['page_id'],
            clientId: $validated['client_id'],
            pageType: $validated['page_type'],
            auctionId: $auctionId,
        );

        $response = [
            'presence' => [
                'online_users' => Presence::onlineUsers(),
                'watcher_count' => $auctionId !== null ? Presence::watchersForAuction($auctionId) : null,
            ],
        ];

        if ($validated['page_type'] === 'home') {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Auction> $auctions */
            $auctions = Auction::query()
                ->with(['seller:id,username', 'bids', 'images'])
                ->orderByRaw("CASE WHEN status = 'active' AND ends_at > ? THEN 1 ELSE 0 END DESC", [now()])
                ->orderByDesc('created_at')
                ->get();

            $this->auctionService->loadWatcherCounts($auctions);
            $auctions = $auctions->sortByDesc('watcher_count')->values();

            $response['auctions'] = $auctions->map(
                fn(Auction $auction) => $this->auctionService->auctionResponse($auction),
            );
            $response['stats'] = $this->statsService->getStats();
        } elseif ($validated['page_type'] === 'auction' && $auctionId) {
            /** @var Auction|null $auction */
            $auction = Auction::query()
                ->with(['seller:id,username', 'bids.user:id,username', 'images', 'questions.user:id,username'])
                ->find($auctionId);

            if ($auction) {
                $auction->setAttribute('watcher_count', Presence::watchersForAuction($auctionId));
                $response['auction'] = $this->auctionService->auctionResponse($auction, withBids: true);
            }
        }

        return response()->json($response);
    }
}
