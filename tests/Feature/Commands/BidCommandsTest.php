<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_bid_requires_a_selector(): void
    {
        $this
            ->artisan('app:delete-bid')
            ->expectsOutput('You must specify a bid ID, --user, or --auction.')
            ->assertExitCode(0);
    }

    public function test_delete_bid_by_id_with_confirmation(): void
    {
        $auction = $this->createAuction();
        $bid = $this->createBid($auction);

        $this
            ->artisan('app:delete-bid', ['id' => $bid->id])
            ->expectsConfirmation('Delete 1 bid(s)?', 'yes')
            ->assertExitCode(0);

        $this->assertSoftDeleted('bids', ['id' => $bid->id]);
    }

    public function test_delete_bid_by_user_and_auction_with_force(): void
    {
        $user = $this->createUser(['username' => 'bidder1']);
        $auction = $this->createAuction();
        $bid = $this->createBid($auction, $user);

        $this->artisan('app:delete-bid', [
            '--user' => 'bidder1',
            '--auction' => (string) $auction->id,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSoftDeleted('bids', ['id' => $bid->id]);
    }

    public function test_delete_bid_reports_no_matches(): void
    {
        $this
            ->artisan('app:delete-bid', ['id' => 999999])
            ->expectsOutput('No bids found matching criteria.')
            ->assertExitCode(0);
    }

    public function test_update_bid_updates_amount_and_quantity(): void
    {
        $auction = $this->createAuction();
        $bid = $this->createBid($auction, null, ['amount' => '20.00', 'quantity' => 1]);

        $this
            ->artisan('app:update-bid', ['id' => $bid->id, '--amount' => '25.00', '--quantity' => '2'])
            ->expectsConfirmation('Apply these changes?', 'yes')
            ->assertExitCode(0);

        $fresh = $bid->fresh();
        $this->assertSame('25.00', $fresh->amount);
        $this->assertSame(2, $fresh->quantity);
    }

    public function test_update_bid_requires_amount_or_quantity(): void
    {
        $auction = $this->createAuction();
        $bid = $this->createBid($auction);

        $this
            ->artisan('app:update-bid', ['id' => $bid->id])
            ->expectsOutput('You must specify at least one of --amount or --quantity.')
            ->assertExitCode(0);
    }

    public function test_update_bid_fails_for_unknown_bid(): void
    {
        $this->artisan('app:update-bid', ['id' => 999999, '--amount' => '10.00'])->assertExitCode(0);
    }

    public function test_list_bids_shows_bids_for_an_auction(): void
    {
        $auction = $this->createAuction();
        $this->createBid($auction);

        $this->artisan('app:list-bids', ['id' => $auction->id])->assertExitCode(0);
    }

    public function test_list_bids_reports_when_none_found(): void
    {
        $auction = $this->createAuction();

        $this->artisan('app:list-bids', ['id' => $auction->id])->expectsOutput('No bids found.')->assertExitCode(0);
    }

    public function test_list_bids_fails_for_unknown_auction(): void
    {
        $this->artisan('app:list-bids', ['id' => 999999])->assertExitCode(0);
    }
}
