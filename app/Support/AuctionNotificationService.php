<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\User;

class AuctionNotificationService
{
    public function __construct(
        protected AuctionService $auctionService,
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function sendNewAuctionNotification(Auction $auction): void
    {
        $subscribedUsers = User::query()
            ->whereHas('pushSubscriptions')
            ->where('id', '!=', $auction->seller_id)
            ->get();

        if ($subscribedUsers->isEmpty()) {
            return;
        }

        $this->pushNotificationService->sendToUsers($subscribedUsers, [
            'body' => sprintf('New auction: "%s"', $auction->title),
            'tag' => "auction-new-{$auction->id}",
            'url' => "/auctions/{$auction->id}",
            'data' => [
                'auctionId' => $auction->id,
                'kind' => 'new_auction',
            ],
        ]);
    }

    /**
     * @param array<int, int> $previousAllocations
     * @param array<int, int> $currentAllocations
     */
    public function sendOverbidNotifications(
        Auction $auction,
        array $previousAllocations,
        array $currentAllocations,
        int $triggeringUserId,
    ): void {
        $userIds = [];

        foreach ($previousAllocations as $userId => $wonQuantity) {
            if ($userId === $triggeringUserId) {
                continue;
            }

            if ($wonQuantity > 0 && ($currentAllocations[$userId] ?? 0) === 0) {
                $userIds[] = $userId;
            }
        }

        if ($userIds === []) {
            return;
        }

        $users = User::query()->whereIn('id', $userIds)->get();

        $this->pushNotificationService->sendToUsers($users, [
            'body' => sprintf('You\'ve been overbid on "%s"!', $auction->title),
            'tag' => "auction-overbid-{$auction->id}",
            'url' => "/auctions/{$auction->id}",
            'data' => [
                'auctionId' => $auction->id,
                'kind' => 'overbid',
            ],
        ]);
    }

    public function sendEndingSoonNotifications(Auction $auction): void
    {
        $auction->loadMissing('bids.user:id,username');

        $latestBids = $this->auctionService->latestBids($auction);

        if ($latestBids->isEmpty()) {
            return;
        }

        /** @var list<User> $bidders */
        $bidders = $latestBids->pluck('user')->filter()->values()->all();

        $this->pushNotificationService->sendToUsers($bidders, [
            'body' => sprintf('"%s" is ending soon!', $auction->title),
            'tag' => "auction-ending-soon-{$auction->id}",
            'url' => "/auctions/{$auction->id}",
            'data' => [
                'auctionId' => $auction->id,
                'kind' => 'ending_soon',
            ],
        ]);
    }

    public function sendAuctionClosedNotifications(Auction $auction): void
    {
        $auction->loadMissing('bids.user:id,username');

        $allocation = $this->auctionService->allocate($auction);
        $userAllocations = $this->auctionService->allocationByUser($auction, $allocation);
        $latestBids = $this->auctionService->latestBids($auction);

        if ($latestBids->isEmpty()) {
            return;
        }

        /** @var list<User> $winners */
        $winners = [];
        /** @var list<User> $losers */
        $losers = [];
        /** @var list<User> $participants */
        $participants = [];

        foreach ($latestBids as $bid) {
            if (!$bid->user) {
                continue;
            }

            if ($auction->status === 'cancelled') {
                $participants[] = $bid->user;
                continue;
            }

            if (($userAllocations[$bid->user_id] ?? 0) > 0) {
                $winners[] = $bid->user;
            } else {
                $losers[] = $bid->user;
            }
        }

        if ($participants !== []) {
            $this->pushNotificationService->sendToUsers($participants, [
                'body' => sprintf('Auction "%s" was cancelled.', $auction->title),
                'tag' => "auction-result-{$auction->id}",
                'url' => "/auctions/{$auction->id}",
                'data' => [
                    'auctionId' => $auction->id,
                    'kind' => 'cancelled',
                ],
            ]);

            return;
        }

        if ($winners !== []) {
            $this->pushNotificationService->sendToUsers($winners, [
                'body' => sprintf('You won "%s"!', $auction->title),
                'tag' => "auction-result-{$auction->id}",
                'url' => "/auctions/{$auction->id}",
                'data' => [
                    'auctionId' => $auction->id,
                    'kind' => 'won',
                ],
            ]);
        }

        if ($losers !== []) {
            $this->pushNotificationService->sendToUsers($losers, [
                'body' => sprintf('Auction "%s" has ended — you didn\'t win.', $auction->title),
                'tag' => "auction-result-{$auction->id}",
                'url' => "/auctions/{$auction->id}",
                'data' => [
                    'auctionId' => $auction->id,
                    'kind' => 'lost',
                ],
            ]);
        }
    }
}
