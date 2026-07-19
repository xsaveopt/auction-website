<?php

namespace Tests\Feature\Commands;

use App\Models\Auction;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionLifecycleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocate_shows_results_for_an_auction_with_bids(): void
    {
        $auction = $this->createAuction(null, ['quantity' => 2]);
        $this->createBid($auction, null, ['amount' => '30.00', 'quantity' => 2]);

        $this->artisan('app:allocate', ['id' => $auction->id])->assertExitCode(0);
    }

    public function test_allocate_warns_when_auction_has_no_bids(): void
    {
        $auction = $this->createAuction();

        $this
            ->artisan('app:allocate', ['id' => $auction->id])
            ->expectsOutputToContain('No bids on this auction.')
            ->assertExitCode(0);
    }

    public function test_allocate_fails_for_unknown_auction(): void
    {
        $this->artisan('app:allocate', ['id' => 999999])->assertExitCode(0);
    }

    public function test_end_auction_ends_an_active_auction(): void
    {
        $auction = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);

        $this
            ->artisan('app:end-auction', ['id' => $auction->id])
            ->expectsConfirmation('Set this auction to "ended"?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('ended', $auction->fresh()->status);
    }

    public function test_end_auction_can_cancel_instead(): void
    {
        $auction = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);

        $this
            ->artisan('app:end-auction', ['id' => $auction->id, '--cancel' => true])
            ->expectsConfirmation('Set this auction to "cancelled"?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('cancelled', $auction->fresh()->status);
    }

    public function test_end_auction_fails_for_unknown_auction(): void
    {
        $this->artisan('app:end-auction', ['id' => 999999])->assertExitCode(0);
    }

    public function test_reactivate_auction_reactivates_an_ended_auction(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);

        $this
            ->artisan('app:reactivate-auction', ['id' => $auction->id])
            ->expectsConfirmation('Reactivate this auction?', 'yes')
            ->assertExitCode(0);

        $fresh = $auction->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertTrue($fresh->ends_at->isFuture());
    }

    public function test_reactivate_auction_warns_when_already_active(): void
    {
        $auction = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);

        $this
            ->artisan('app:reactivate-auction', ['id' => $auction->id])
            ->expectsOutputToContain('is already active.')
            ->assertExitCode(0);
    }

    public function test_reactivate_auction_fails_for_unknown_auction(): void
    {
        $this->artisan('app:reactivate-auction', ['id' => 999999])->assertExitCode(0);
    }

    public function test_extend_auction_extends_the_end_time(): void
    {
        $originalEnd = now()->addHour();
        $auction = $this->createAuction(null, ['ends_at' => $originalEnd]);

        $this
            ->artisan('app:extend-auction', ['id' => $auction->id, 'time' => '+1 hour'])
            ->expectsConfirmation('Apply this change?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($auction->fresh()->ends_at->greaterThan($originalEnd));
    }

    public function test_extend_auction_rejects_invalid_time_modifier(): void
    {
        $auction = $this->createAuction();

        $this->artisan('app:extend-auction', ['id' => $auction->id, 'time' => 'not-a-real-modifier!!'])->assertExitCode(
            0,
        );
    }

    public function test_extend_auction_fails_for_unknown_auction(): void
    {
        $this->artisan('app:extend-auction', ['id' => 999999, 'time' => '+1 hour'])->assertExitCode(0);
    }

    public function test_finalize_ended_auctions_finalizes_expired_active_auctions(): void
    {
        $expired = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->subMinute()]);
        $stillActive = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);

        $this
            ->artisan('app:finalize-ended-auctions')
            ->expectsOutput('Finalized 1 expired auction(s).')
            ->assertExitCode(0);

        $this->assertSame('ended', $expired->fresh()->status);
        $this->assertSame('active', $stillActive->fresh()->status);
    }

    public function test_bulk_update_requires_ids_or_all_active(): void
    {
        $this
            ->artisan('app:update-auctions')
            ->expectsOutput('You must specify either --ids or --all-active.')
            ->assertExitCode(0);
    }

    public function test_bulk_update_reports_no_matches(): void
    {
        $this
            ->artisan('app:update-auctions', ['--ids' => '999999'])
            ->expectsOutput('No auctions found matching criteria.')
            ->assertExitCode(0);
    }

    public function test_bulk_update_updates_status_by_ids(): void
    {
        $auction = $this->createAuction();

        $this
            ->artisan('app:update-auctions', ['--ids' => (string) $auction->id, '--status' => 'cancelled'])
            ->expectsConfirmation('You are about to update 1 auctions. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('cancelled', $auction->fresh()->status);
    }

    public function test_bulk_update_adds_time_for_all_active(): void
    {
        $endsAt = now()->addHour();
        $auction = $this->createAuction(null, ['status' => 'active', 'ends_at' => $endsAt]);

        $this
            ->artisan('app:update-auctions', ['--all-active' => true, '--add-time' => '+1 day'])
            ->expectsConfirmation('You are about to update 1 auctions. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($auction->fresh()->ends_at->greaterThan($endsAt->copy()->addHours(23)));
    }

    public function test_bulk_update_appends_and_prepends_description(): void
    {
        $auction = $this->createAuction(null, ['description' => 'Base']);

        $this
            ->artisan('app:update-auctions', [
                '--ids' => (string) $auction->id,
                '--append-description' => ' Suffix',
                '--prepend-description' => 'Prefix ',
            ])
            ->expectsConfirmation('You are about to update 1 auctions. Continue?', 'yes')
            ->assertExitCode(0);

        $this->assertSame('Prefix Base Suffix', $auction->fresh()->description);
    }

    public function test_toggle_lock_locks_and_unlocks_the_site(): void
    {
        $this->artisan('app:toggle-lock', ['--message' => 'Down for maintenance'])->assertExitCode(0);

        $settings = SiteSetting::instance()->fresh();
        $this->assertTrue($settings->is_locked);
        $this->assertSame('Down for maintenance', $settings->lock_message);

        $this->artisan('app:toggle-lock')->assertExitCode(0);

        $this->assertFalse(SiteSetting::instance()->fresh()->is_locked);
    }

    public function test_list_auctions_shows_matching_auctions(): void
    {
        $this->createAuction(null, ['title' => 'A Great Auction', 'status' => 'active']);

        $this->artisan('app:list-auctions', ['--status' => 'active', '--search' => 'Great'])->assertExitCode(0);
    }

    public function test_list_auctions_reports_when_none_found(): void
    {
        Auction::query()->delete();

        $this->artisan('app:list-auctions')->expectsOutput('No auctions found.')->assertExitCode(0);
    }
}
