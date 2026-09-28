<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AuctionPolicy
{
    public function bid(User $user, Auction $auction): Response
    {
        return $this->isSeller($user, $auction)
            ? Response::denyWithStatus(422, 'You cannot bid on your own auction.')
            : Response::allow();
    }

    public function askQuestion(User $user, Auction $auction): Response
    {
        return $this->isSeller($user, $auction)
            ? Response::denyWithStatus(422, 'You cannot ask a question on your own auction.')
            : Response::allow();
    }

    public function purchaseLeftover(User $user, Auction $auction): Response
    {
        return $this->isSeller($user, $auction)
            ? Response::deny('You cannot purchase from your own auction.')
            : Response::allow();
    }

    public function offerOnLeftover(User $user, Auction $auction): Response
    {
        return $this->isSeller($user, $auction)
            ? Response::deny('You cannot make an offer on your own auction.')
            : Response::allow();
    }

    private function isSeller(User $user, Auction $auction): bool
    {
        return $auction->seller_id === $user->id;
    }
}
