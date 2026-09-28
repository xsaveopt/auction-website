<?php

namespace Tests\Unit;

use App\Models\Auction;
use App\Support\AuctionFinalizationService;
use App\Support\AuctionNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuctionFinalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_and_cancel_only_transition_active_auctions(): void
    {
        $notificationService = Mockery::mock(AuctionNotificationService::class);
        $notificationService->shouldReceive('sendAuctionClosedNotifications')->twice();

        $service = new AuctionFinalizationService($notificationService);

        $activeAuction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->addHour(),
        ]);
        $endedAuction = $this->createAuction($this->createUser(), [
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $this->assertTrue($service->end($activeAuction));
        $this->assertSame('ended', $this->reload($activeAuction)->status);

        $reactivatedAuction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->addHour(),
        ]);
        $this->assertTrue($service->cancel($reactivatedAuction));
        $this->assertSame('cancelled', $this->reload($reactivatedAuction)->status);

        $this->assertFalse($service->end($endedAuction));
    }

    public function test_finalize_expired_auctions_only_counts_active_expired_rows(): void
    {
        $notificationService = Mockery::mock(AuctionNotificationService::class);
        $notificationService->shouldReceive('sendAuctionClosedNotifications')->once();

        $service = new AuctionFinalizationService($notificationService);

        $expiredAuction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->subMinute(),
        ]);
        $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->addHour(),
        ]);
        $this->createAuction($this->createUser(), [
            'status' => 'cancelled',
            'ends_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, $service->finalizeExpiredAuctions());
        $this->assertSame('ended', $this->reload($expiredAuction)->status);
    }

    public function test_a_stale_instance_cannot_end_an_auction_twice(): void
    {
        $notificationService = Mockery::mock(AuctionNotificationService::class);
        $notificationService->shouldReceive('sendAuctionClosedNotifications')->once();

        $service = new AuctionFinalizationService($notificationService);

        $auction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->subMinute(),
        ]);
        $firstWorker = Auction::query()->findOrFail($auction->id);
        $secondWorker = Auction::query()->findOrFail($auction->id);

        $this->assertTrue($service->end($firstWorker));
        $this->assertFalse($service->end($secondWorker));
        $this->assertSame('ended', $this->reload($auction)->status);
    }

    public function test_finalize_skips_auctions_extended_after_they_were_selected(): void
    {
        $notificationService = Mockery::mock(AuctionNotificationService::class);
        $notificationService->shouldReceive('sendAuctionClosedNotifications')->never();

        $service = new AuctionFinalizationService($notificationService);

        $auction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->subMinute(),
        ]);

        Auction::query()->whereKey($auction->id)->update(['status' => 'ended']);

        $this->assertSame(0, $service->finalizeExpiredAuctions());
    }
}
