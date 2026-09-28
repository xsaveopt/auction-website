<?php

namespace App\Policies;

use App\Models\AuctionQuestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AuctionQuestionPolicy
{
    public function answer(User $user, AuctionQuestion $question): Response
    {
        return $this->manage($user, $question);
    }

    public function delete(User $user, AuctionQuestion $question): Response
    {
        return $this->manage($user, $question);
    }

    private function manage(User $user, AuctionQuestion $question): Response
    {
        if ($user->is_admin || $question->auction?->seller_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('Forbidden.');
    }
}
