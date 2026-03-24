<?php

namespace Tests\Unit;

use App\Support\AuctionNotificationService;
use App\Support\AuctionService;
use App\Support\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuctionNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overbid_notifications_only_target_users_who_lost_their_allocation(): void
    {
        $seller = $this->createUser();
        $loser = $this->createUser();
        $triggeringBidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$loser->id]),
                Mockery::on(function ($payload) use ($auction) {
                    return (
                        $payload['tag'] === "auction-overbid-{$auction->id}"
                        && $payload['data']['kind'] === 'overbid'
                    );
                }),
            );

        $service = new AuctionNotificationService(new AuctionService(), $push);

        $service->sendOverbidNotifications(
            $auction,
            [
                $loser->id => 1,
                $triggeringBidder->id => 1,
            ],
            [
                $loser->id => 0,
                $triggeringBidder->id => 1,
            ],
            $triggeringBidder->id,
        );
    }

    public function test_closed_auction_notifications_split_winners_and_losers(): void
    {
        $seller = $this->createUser();
        $winner = $this->createUser();
        $loser = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, $winner, [
            'amount' => '20.00',
            'quantity' => 1,
        ]);
        $this->createBid($auction, $loser, [
            'amount' => '10.00',
            'quantity' => 1,
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$winner->id]),
                Mockery::on(fn($payload) => $payload['data']['kind'] === 'won'),
            );
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$loser->id]),
                Mockery::on(fn($payload) => $payload['data']['kind'] === 'lost'),
            );

        $service = new AuctionNotificationService(new AuctionService(), $push);

        $service->sendAuctionClosedNotifications($auction->fresh());
    }

    public function test_cancelled_auctions_notify_all_participants_once(): void
    {
        $seller = $this->createUser();
        $firstBidder = $this->createUser();
        $secondBidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'cancelled',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, $firstBidder, ['amount' => '15.00']);
        $this->createBid($auction, $secondBidder, ['amount' => '12.00']);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(
                    fn($users) => (
                        collect($users)->pluck('id')->sort()->values()->all() === [$firstBidder->id, $secondBidder->id]
                    ),
                ),
                Mockery::on(fn($payload) => $payload['data']['kind'] === 'cancelled'),
            );

        $service = new AuctionNotificationService(new AuctionService(), $push);

        $service->sendAuctionClosedNotifications($auction->fresh());
    }
}
