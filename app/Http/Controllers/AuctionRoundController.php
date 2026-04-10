<?php

namespace App\Http\Controllers;

use App\Models\AuctionRound;
use App\Models\AuditLog;
use App\Support\AuctionFinalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionRoundController extends Controller
{
    public function __construct(
        protected AuctionFinalizationService $auctionFinalizationService,
    ) {}

    public function current(): JsonResponse
    {
        $active = AuctionRound::where('status', 'active')->latest()->first();
        $ended = AuctionRound::where('status', 'ended')->orderByDesc('ends_at')->get();

        return response()->json([
            'active' => $active ? $this->roundResponse($active) : null,
            'ended' => $ended->map(fn(AuctionRound $r) => $this->roundResponse($r))->values(),
        ]);
    }

    public function index(): JsonResponse
    {
        $rounds = AuctionRound::query()
            ->withCount(['auctions'])
            ->latest()
            ->get();

        return response()->json([
            'rounds' => $rounds->map(fn(AuctionRound $round) => array_merge($this->roundResponse($round), [
                'auction_count' => $round->auctions_count,
            ])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{name: string} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (AuctionRound::where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'An active round already exists. Close it before creating a new one.',
            ], 422);
        }

        $round = AuctionRound::create([
            'name' => $validated['name'],
            'status' => 'active',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'round.create', $round, ['name' => $round->name]);

        return response()->json(['round' => $this->roundResponse($round)], 201);
    }

    public function close(Request $request, AuctionRound $round): JsonResponse
    {
        if ($round->status !== 'active') {
            return response()->json(['message' => 'This round is not active.'], 422);
        }

        $activeAuctions = $round->auctions()->where('status', 'active')->where('ends_at', '>', now())->get();

        foreach ($activeAuctions as $auction) {
            $auction->load(['bids.user']);
            $this->auctionFinalizationService->end($auction);
        }

        $round->update([
            'status' => 'ended',
            'ends_at' => now(),
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        AuditLog::record($user, 'round.close', $round, [
            'name' => $round->name,
            'auctions_ended' => $activeAuctions->count(),
        ]);

        return response()->json(['round' => $this->roundResponse($round)]);
    }

    /** @return array<string, mixed> */
    private function roundResponse(AuctionRound $round): array
    {
        return [
            'id' => $round->id,
            'name' => $round->name,
            'status' => $round->status,
            'ends_at' => $round->ends_at?->format('Y-m-d\TH:i:sP'),
            'created_at' => $round->created_at?->format('Y-m-d\TH:i:sP'),
        ];
    }
}
