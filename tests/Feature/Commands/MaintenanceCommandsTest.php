<?php

namespace Tests\Feature\Commands;

use App\Models\LeftoverPurchase;
use App\Models\PresenceHeartbeat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_images_reports_clean_state(): void
    {
        Storage::fake('public');

        $this
            ->artisan('app:cleanup-images')
            ->expectsOutput('No orphaned images found. Everything is clean.')
            ->assertExitCode(0);
    }

    public function test_cleanup_images_dry_run_lists_but_does_not_delete(): void
    {
        Storage::fake('public');
        $auction = $this->createAuction();
        $image = $this->createImage($auction, ['path' => "auctions/{$auction->id}/missing.jpg"]);
        Storage::disk('public')->put("auctions/{$auction->id}/orphan.jpg", 'data');

        $this->artisan('app:cleanup-images', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('auction_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists("auctions/{$auction->id}/orphan.jpg");
    }

    public function test_cleanup_images_force_deletes_orphans(): void
    {
        Storage::fake('public');
        $auction = $this->createAuction();
        $image = $this->createImage($auction, ['path' => "auctions/{$auction->id}/missing.jpg"]);
        Storage::disk('public')->put("auctions/{$auction->id}/orphan.jpg", 'data');

        $this->artisan('app:cleanup-images', ['--force' => true])->assertExitCode(0);

        $this->assertDatabaseMissing('auction_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing("auctions/{$auction->id}/orphan.jpg");
    }

    public function test_clear_presence_deletes_all_records(): void
    {
        $this->createUser();
        PresenceHeartbeat::query()->create([
            'page_id' => 'page-1',
            'client_id' => 'client-1',
            'page_type' => 'auction',
            'auction_id' => $this->createAuction()->id,
            'user_id' => $this->createUser()->id,
            'last_seen_at' => now(),
        ]);

        $this->artisan('app:clear-presence')->assertExitCode(0);

        $this->assertSame(0, PresenceHeartbeat::count());
    }

    public function test_clear_presence_stale_only_deletes_old_records(): void
    {
        $auction = $this->createAuction();
        $fresh = PresenceHeartbeat::query()->create([
            'page_id' => 'page-fresh',
            'client_id' => 'client-fresh',
            'page_type' => 'auction',
            'auction_id' => $auction->id,
            'user_id' => $this->createUser()->id,
            'last_seen_at' => now(),
        ]);
        $stale = PresenceHeartbeat::query()->create([
            'page_id' => 'page-stale',
            'client_id' => 'client-stale',
            'page_type' => 'auction',
            'auction_id' => $auction->id,
            'user_id' => $this->createUser()->id,
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $this->artisan('app:clear-presence', ['--stale' => true])->assertExitCode(0);

        $this->assertDatabaseHas('presence_heartbeats', ['page_id' => $fresh->page_id]);
        $this->assertDatabaseMissing('presence_heartbeats', ['page_id' => $stale->page_id]);
    }

    public function test_clear_presence_reports_when_empty(): void
    {
        $this->artisan('app:clear-presence')->expectsOutput('No presence records found.')->assertExitCode(0);
    }

    public function test_clear_sessions_for_specific_user(): void
    {
        $user = $this->createUser(['username' => 'sessioned']);
        DB::table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $user->id,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this
            ->artisan('app:clear-sessions', ['--user' => 'sessioned'])
            ->expectsConfirmation('Clear all sessions for "sessioned"?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_clear_sessions_fails_for_unknown_user(): void
    {
        $this->artisan('app:clear-sessions', ['--user' => 'ghost'])->assertExitCode(0);
    }

    public function test_clear_sessions_force_clears_all(): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-2',
            'user_id' => null,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('app:clear-sessions', ['--force' => true])->assertExitCode(0);

        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_notify_ending_soon_marks_auctions_as_notified(): void
    {
        $auction = $this->createAuction(null, [
            'status' => 'active',
            'ends_at' => now()->addMinutes(5),
            'ending_soon_notified' => false,
        ]);
        $this->createBid($auction);

        $this->artisan('app:notify-ending-soon', ['--minutes' => 15])->assertExitCode(0);

        $this->assertTrue($auction->fresh()->ending_soon_notified);
    }

    public function test_notify_ending_soon_skips_auctions_outside_window(): void
    {
        $auction = $this->createAuction(null, [
            'status' => 'active',
            'ends_at' => now()->addHours(2),
            'ending_soon_notified' => false,
        ]);

        $this
            ->artisan('app:notify-ending-soon', ['--minutes' => 15])
            ->expectsOutput('Sent ending-soon notifications for 0 auction(s).')
            ->assertExitCode(0);

        $this->assertFalse($auction->fresh()->ending_soon_notified);
    }

    public function test_generate_vapid_keys_prints_a_key_pair(): void
    {
        $this->artisan('app:generate-vapid-keys')->assertExitCode(0);
    }

    public function test_reset_price_offer_purchases_reports_none_found(): void
    {
        $this
            ->artisan('app:reset-price-offer-purchases')
            ->expectsOutput('No price-offer-based purchases found.')
            ->assertExitCode(0);
    }

    public function test_reset_price_offer_purchases_deletes_and_resets_offers(): void
    {
        $auction = $this->createAuction();
        $offer = $this->createLeftoverPriceOffer($auction, null, ['status' => 'accepted']);
        $purchase = $this->createLeftoverPurchase($auction, null, ['leftover_price_offer_id' => $offer->id]);

        $this
            ->artisan('app:reset-price-offer-purchases')
            ->expectsConfirmation('Delete these purchases and reset their offers to pending?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('leftover_purchases', ['id' => $purchase->id]);
        $this->assertDatabaseHas('leftover_price_offers', ['id' => $offer->id, 'status' => 'pending']);
    }

    public function test_reset_price_offer_purchases_declining_confirmation_keeps_data(): void
    {
        $auction = $this->createAuction();
        $offer = $this->createLeftoverPriceOffer($auction, null, ['status' => 'accepted']);
        $purchase = $this->createLeftoverPurchase($auction, null, ['leftover_price_offer_id' => $offer->id]);

        $this
            ->artisan('app:reset-price-offer-purchases')
            ->expectsConfirmation('Delete these purchases and reset their offers to pending?', 'no')
            ->assertExitCode(1);

        $this->assertDatabaseHas('leftover_purchases', ['id' => $purchase->id]);
    }

    public function test_generate_quote_creates_a_pdf(): void
    {
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4');
        $pdf->shouldReceive('save')->once();

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.quote', Mockery::on(fn(array $data) => $data['winner']['username'] === 'Jane Buyer'))
            ->andReturn($pdf);

        $this
            ->artisan('app:generate-quote', [
                '--title' => 'Vintage Chair',
                '--buyer' => 'Jane Buyer',
                '--price' => '50.00',
                '--quantity' => 2,
            ])
            ->expectsConfirmation('Generate this quote?', 'yes')
            ->assertExitCode(0);
    }

    public function test_generate_quote_requires_title_buyer_and_price(): void
    {
        $this
            ->artisan('app:generate-quote', ['--title' => 'Only Title', '--buyer' => '', '--price' => ''])
            ->expectsOutput('Title, buyer, and price are required.')
            ->assertExitCode(0);
    }
}
