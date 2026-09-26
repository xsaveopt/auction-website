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

    public function test_new_auction_notification_targets_subscribed_users_except_the_seller(): void
    {
        $seller = $this->createUser();
        $subscriber = $this->createUser();
        $this->createUser();
        $this->createPushSubscription($seller);
        $this->createPushSubscription($subscriber);
        $auction = $this->createAuction($seller, ['title' => 'Monitor']);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$subscriber->id]),
                [
                    'body' => 'New auction: "Monitor"',
                    'tag' => "auction-new-{$auction->id}",
                    'url' => "/auctions/{$auction->id}",
                    'data' => [
                        'auctionId' => $auction->id,
                        'kind' => 'new_auction',
                    ],
                ],
            );

        new AuctionNotificationService(new AuctionService(), $push)->sendNewAuctionNotification($auction);
    }

    public function test_new_auction_notification_is_skipped_without_subscribers(): void
    {
        $seller = $this->createUser();
        $this->createPushSubscription($seller);
        $this->createUser();
        $auction = $this->createAuction($seller);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToUsers');

        new AuctionNotificationService(new AuctionService(), $push)->sendNewAuctionNotification($auction);
    }

    public function test_question_answered_notification_targets_the_asker(): void
    {
        $asker = $this->createUser();
        $auction = $this->createAuction(null, ['title' => 'Keyboard']);
        $question = $this->createQuestion($auction, $asker, [
            'answer' => 'Yes',
            'answered_at' => now(),
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$asker->id]),
                [
                    'body' => 'Your question on "Keyboard" has been answered.',
                    'tag' => "question-answered-{$question->id}",
                    'url' => "/auctions/{$auction->id}",
                    'data' => [
                        'auctionId' => $auction->id,
                        'kind' => 'question_answered',
                    ],
                ],
            );

        new AuctionNotificationService(new AuctionService(), $push)->sendQuestionAnsweredNotification(
            $question->fresh(),
        );
    }

    public function test_offer_accepted_notification_targets_the_offer_owner(): void
    {
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, ['title' => 'Headset']);
        $offer = $this->createLeftoverPriceOffer($auction, $buyer, ['status' => 'accepted']);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$buyer->id]),
                [
                    'body' => 'Your price offer on "Headset" has been accepted!',
                    'tag' => "offer-accepted-{$offer->id}",
                    'url' => "/auctions/{$auction->id}",
                    'data' => [
                        'auctionId' => $auction->id,
                        'kind' => 'offer_accepted',
                    ],
                ],
            );

        new AuctionNotificationService(new AuctionService(), $push)->sendOfferAcceptedNotification($offer->fresh());
    }

    public function test_offer_rejected_notification_targets_the_offer_owner(): void
    {
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, ['title' => 'Webcam']);
        $offer = $this->createLeftoverPriceOffer($auction, $buyer, ['status' => 'rejected']);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(fn($users) => collect($users)->pluck('id')->all() === [$buyer->id]),
                [
                    'body' => 'Your price offer on "Webcam" was declined.',
                    'tag' => "offer-rejected-{$offer->id}",
                    'url' => "/auctions/{$auction->id}",
                    'data' => [
                        'auctionId' => $auction->id,
                        'kind' => 'offer_rejected',
                    ],
                ],
            );

        new AuctionNotificationService(new AuctionService(), $push)->sendOfferRejectedNotification($offer->fresh());
    }

    public function test_offer_notifications_are_skipped_when_the_auction_is_gone(): void
    {
        $auction = $this->createAuction();
        $offer = $this->createLeftoverPriceOffer($auction);
        $auction->delete();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToUsers');

        $service = new AuctionNotificationService(new AuctionService(), $push);
        $service->sendOfferAcceptedNotification($offer->fresh());
        $service->sendOfferRejectedNotification($offer->fresh());
    }

    public function test_ending_soon_notification_targets_every_bidder_including_losers(): void
    {
        $first = $this->createUser();
        $second = $this->createUser();
        $auction = $this->createAuction(null, [
            'title' => 'Tablet',
            'quantity' => 1,
        ]);
        $this->createBid($auction, $first, ['amount' => '12.00']);
        $this->createBid($auction, $second, ['amount' => '13.00']);

        $push = Mockery::mock(PushNotificationService::class);
        $push
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(
                Mockery::on(
                    fn($users) => collect($users)->pluck('id')->sort()->values()->all() === [$first->id, $second->id],
                ),
                [
                    'body' => '"Tablet" is ending soon!',
                    'tag' => "auction-ending-soon-{$auction->id}",
                    'url' => "/auctions/{$auction->id}",
                    'data' => [
                        'auctionId' => $auction->id,
                        'kind' => 'ending_soon',
                    ],
                ],
            );

        new AuctionNotificationService(new AuctionService(), $push)->sendEndingSoonNotifications($auction->fresh());
    }

    public function test_ending_soon_notification_is_skipped_without_bids(): void
    {
        $auction = $this->createAuction();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToUsers');

        new AuctionNotificationService(new AuctionService(), $push)->sendEndingSoonNotifications($auction->fresh());
    }
}
