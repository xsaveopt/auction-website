<?php

namespace App\Http\Controllers;

use App\Support\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PresenceController extends Controller
{
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

        return response()->json([
            'presence' => [
                'online_users' => Presence::onlineUsers(),
                'watcher_count' => $auctionId !== null ? Presence::watchersForAuction($auctionId) : null,
            ],
        ]);
    }
}
