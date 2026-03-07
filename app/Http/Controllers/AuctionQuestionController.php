<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionQuestionController extends Controller
{
    public function store(Request $request, Auction $auction): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($auction->seller_id === $user->id) {
            return response()->json(['message' => 'You cannot ask a question on your own auction.'], 422);
        }

        /** @var array{question: string} $validated */
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $question = $auction
            ->questions()
            ->make([
                'question' => $validated['question'],
            ]);
        $question->user()->associate($user);
        $question->save();

        $question->load('user:id,username');

        return response()->json([
            'question' => $this->questionResponse($question),
        ], 201);
    }

    public function update(Request $request, AuctionQuestion $question): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $question->loadMissing(['auction', 'user:id,username']);

        /** @var \App\Models\Auction $auction */
        $auction = $question->auction;

        if ($auction->seller_id !== $user->id && !$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var array{answer: string} $validated */
        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:4000'],
        ]);

        $question->update([
            'answer' => $validated['answer'],
            'answered_at' => now(),
        ]);

        /** @var AuctionQuestion $fresh */
        $fresh = $question->fresh(['user:id,username']);

        return response()->json([
            'question' => $this->questionResponse($fresh),
        ]);
    }

    public function destroy(Request $request, AuctionQuestion $question): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $question->loadMissing('auction');

        /** @var \App\Models\Auction $auction */
        $auction = $question->auction;

        if ($auction->seller_id !== $user->id && !$user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function questionResponse(AuctionQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question' => $question->question,
            'answer' => $question->answer,
            'answered_at' => $question->answered_at?->toISOString(),
            'user' => [
                'id' => $question->user?->id,
                'username' => $question->user?->username,
            ],
            'created_at' => $question->created_at?->toISOString(),
        ];
    }
}
