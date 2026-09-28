<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuctionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_an_auction_while_capping_max_per_bidder(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();

        $createResponse = $this->actingAs($admin)->postJson('/api/auctions', [
            'title' => 'Generator lot',
            'description' => 'Backup generators',
            'starting_price' => 100,
            'quantity' => 2,
            'max_per_bidder' => 5,
            'ends_at' => now()->addDay()->toISOString(),
            'category_id' => $category->id,
        ]);

        $auctionId = $createResponse->json('auction.id');
        $this->assertIsInt($auctionId);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('auction.max_per_bidder', 2)
            ->assertJsonPath('auction.category.id', $category->id);

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auctionId}", [
                'title' => 'Generator lot updated',
                'description' => 'Updated description',
                'starting_price' => 150,
                'quantity' => 3,
                'max_per_bidder' => 10,
                'ends_at' => now()->addDays(2)->toISOString(),
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('auction.max_per_bidder', 3);

        $this->assertDatabaseHas('auctions', [
            'id' => $auctionId,
            'title' => 'Generator lot updated',
            'max_per_bidder' => 3,
        ]);
    }

    public function test_non_admin_users_cannot_create_auctions(): void
    {
        $user = $this->createUser();

        $this
            ->actingAs($user)
            ->postJson('/api/auctions', [
                'title' => 'Blocked auction',
                'description' => 'Description',
                'starting_price' => 50,
                'quantity' => 1,
                'max_per_bidder' => 1,
                'ends_at' => now()->addDay()->toISOString(),
            ])
            ->assertForbidden();
    }

    public function test_my_auctions_groups_active_won_lost_and_purchased_auctions(): void
    {
        $user = $this->createUser();
        $seller = $this->createUser();
        $competitor = $this->createUser();

        $activeAuction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'active',
            'ends_at' => now()->addDay(),
        ]);
        $wonAuction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $lostAuction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $purchasedAuction = $this->createAuction($seller, [
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $this->createBid($activeAuction, $user, ['amount' => '11.00']);
        $this->createBid($wonAuction, $user, ['amount' => '20.00']);
        $this->createBid($wonAuction, $competitor, ['amount' => '15.00']);
        $this->createBid($lostAuction, $user, ['amount' => '12.00']);
        $this->createBid($lostAuction, $competitor, ['amount' => '18.00']);
        $this->createLeftoverPurchase($purchasedAuction, $user, ['quantity' => 1]);

        $this
            ->actingAs($user)
            ->getJson('/api/my-auctions')
            ->assertOk()
            ->assertJsonCount(1, 'active')
            ->assertJsonCount(1, 'won')
            ->assertJsonCount(1, 'lost')
            ->assertJsonCount(1, 'purchased');
    }

    public function test_admin_can_view_the_ended_auction_summary(): void
    {
        $admin = $this->createAdmin();
        $seller = $this->createUser();
        $winner = $this->createUser();

        $auction = $this->createAuction($seller, [
            'starting_price' => '10.00',
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $bid = $this->createBid($auction, $winner, [
            'amount' => '12.00',
            'quantity' => 1,
        ]);
        $this->createLeftoverPurchase($auction, $this->createUser(), [
            'quantity' => 1,
            'price_per_item' => '7.50',
        ]);

        $this
            ->actingAs($admin)
            ->getJson('/api/auctions/ended')
            ->assertOk()
            ->assertJsonPath('summary.ended_auctions', 1)
            ->assertJsonPath('summary.auctions_with_sales', 1)
            ->assertJsonPath('summary.sold_items', 2)
            ->assertJsonPath('auctions.0.bids.0.id', $bid->id);
    }

    public function test_index_and_show_are_public_and_include_auction_data(): void
    {
        $seller = $this->createUser();
        $auction = $this->createAuction($seller);
        $bid = $this->createBid($auction, $this->createUser(), ['amount' => '25.00']);

        $this->getJson('/api/auctions')->assertOk()->assertJsonPath('auctions.0.id', $auction->id);

        $this
            ->getJson("/api/auctions/{$auction->id}")
            ->assertOk()
            ->assertJsonPath('auction.id', $auction->id)
            ->assertJsonPath('auction.bids.0.id', $bid->id)
            ->assertJsonPath('auction.seller.id', $seller->id);
    }

    public function test_destroy_soft_deletes_the_auction_and_keeps_its_images(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        $auction = $this->createAuction($admin);
        $path = "auctions/{$auction->id}/example.jpg";
        Storage::disk('public')->put($path, 'image');
        $auction
            ->images()
            ->create([
                'path' => $path,
                'sort_order' => 1,
            ]);

        $this
            ->actingAs($admin)
            ->deleteJson("/api/auctions/{$auction->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Auction deleted.');

        $this->assertSoftDeleted('auctions', ['id' => $auction->id]);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('auction_images', ['auction_id' => $auction->id, 'path' => $path]);
    }

    public function test_leftovers_lists_only_non_active_auctions_with_unsold_items(): void
    {
        $withLeftovers = $this->createAuction(null, [
            'quantity' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($withLeftovers, null, ['quantity' => 1]);
        $this->createLeftoverPurchase($withLeftovers, null, ['quantity' => 1]);

        $soldOut = $this->createAuction(null, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHours(2),
        ]);
        $this->createBid($soldOut, null, ['quantity' => 1]);

        $this->createAuction(null, ['quantity' => 5, 'status' => 'active', 'ends_at' => now()->addDay()]);

        $this
            ->actingAs($this->createAdmin())
            ->getJson('/api/auctions/leftovers')
            ->assertOk()
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.id', $withLeftovers->id)
            ->assertJsonPath('auctions.0.leftover_quantity', 1);
    }

    public function test_leftovers_include_expired_auctions_and_filter_by_round(): void
    {
        $round = $this->createRound(['status' => 'active']);
        $expired = $this->createAuction(null, [
            'quantity' => 2,
            'status' => 'active',
            'ends_at' => now()->subMinute(),
            'auction_round_id' => $round->id,
        ]);
        $this->createAuction(null, [
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $admin = $this->createAdmin();

        $this->actingAs($admin)->getJson('/api/auctions/leftovers')->assertOk()->assertJsonCount(2, 'auctions');

        $this->assertSame('active', $this->reload($expired)->status);

        $this
            ->actingAs($admin)
            ->getJson("/api/auctions/leftovers?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.id', $expired->id)
            ->assertJsonPath('auctions.0.leftover_quantity', 2);
    }

    public function test_override_sales_reduce_and_clear_leftovers_of_a_closed_round(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $round = $this->createRound(['status' => 'ended']);
        $auction = $this->createAuction(null, [
            'quantity' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
            'auction_round_id' => $round->id,
        ]);
        $this->createBid($auction, null, ['quantity' => 1]);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', [
                'username' => $buyer->username,
                'items' => [['auction_id' => $auction->id, 'quantity' => 1, 'price_per_item' => '4.00']],
            ])
            ->assertCreated();

        $this
            ->actingAs($admin)
            ->getJson("/api/auctions/leftovers?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.leftover_quantity', 1);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', [
                'username' => $buyer->username,
                'items' => [['auction_id' => $auction->id, 'quantity' => 1, 'price_per_item' => '4.00']],
            ])
            ->assertCreated();

        $this
            ->actingAs($admin)
            ->getJson("/api/auctions/leftovers?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(0, 'auctions');
    }

    public function test_leftovers_is_admin_only(): void
    {
        $this->getJson('/api/auctions/leftovers')->assertUnauthorized();
        $this->actingAs($this->createUser())->getJson('/api/auctions/leftovers')->assertForbidden();
    }

    public function test_ended_summary_totals_bid_and_leftover_revenue_with_and_without_tax(): void
    {
        $settings = \App\Models\SiteSetting::instance();
        $settings->invoice_btw_percentage = 21.0;
        $settings->save();

        $admin = $this->createAdmin();
        $bidAuction = $this->createAuction(null, [
            'quantity' => 3,
            'max_per_bidder' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHours(2),
        ]);
        $this->createBid($bidAuction, null, ['amount' => '20.00', 'quantity' => 2]);
        $this->createBid($bidAuction, null, ['amount' => '15.00', 'quantity' => 1]);
        $this->createBid($bidAuction, null, ['amount' => '11.00', 'quantity' => 1]);

        $leftoverAuction = $this->createAuction(null, [
            'starting_price' => '10.00',
            'quantity' => 4,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPurchase($leftoverAuction, null, ['quantity' => 2, 'price_per_item' => '7.50']);
        $this->createLeftoverPriceOffer($leftoverAuction, null, [
            'quantity' => 1,
            'offered_price_per_item' => '6.00',
            'status' => 'accepted',
        ]);
        $this->createLeftoverPriceOffer($leftoverAuction, null, [
            'quantity' => 1,
            'offered_price_per_item' => '5.00',
            'status' => 'pending',
        ]);

        $active = $this->createAuction(null, ['ends_at' => now()->addDay()]);
        $this->createBid($active, null, ['amount' => '50.00']);

        $this
            ->actingAs($admin)
            ->getJson('/api/auctions/ended')
            ->assertOk()
            ->assertJsonCount(2, 'auctions')
            ->assertJsonPath('summary.ended_auctions', 2)
            ->assertJsonPath('summary.auctions_with_sales', 2)
            ->assertJsonPath('summary.sold_items', 6)
            ->assertJsonPath('summary.revenue_after_tax', 66)
            ->assertJsonPath('summary.revenue_before_tax', 54.55);
    }

    public function test_ended_summary_filters_by_round(): void
    {
        $admin = $this->createAdmin();
        $round = $this->createRound();
        $inRound = $this->createAuction(null, [
            'auction_round_id' => $round->id,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);

        $this
            ->actingAs($admin)
            ->getJson("/api/auctions/ended?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.id', $inRound->id)
            ->assertJsonPath('summary.ended_auctions', 1);
    }

    public function test_ended_summary_includes_expired_auctions_before_finalization(): void
    {
        $admin = $this->createAdmin();
        $expired = $this->createAuction(null, ['ends_at' => now()->subMinute()]);
        $this->createBid($expired, null, ['amount' => '12.00']);

        $this
            ->actingAs($admin)
            ->getJson('/api/auctions/ended')
            ->assertOk()
            ->assertJsonPath('auctions.0.id', $expired->id)
            ->assertJsonPath('summary.sold_items', 1);

        $this->assertSame('active', $this->reload($expired)->status);
    }

    public function test_ended_summary_does_not_count_cancelled_auctions_as_sales(): void
    {
        $admin = $this->createAdmin();
        $cancelled = $this->createAuction(null, [
            'status' => 'cancelled',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($cancelled, null, ['amount' => '40.00']);

        $this
            ->actingAs($admin)
            ->getJson('/api/auctions/ended')
            ->assertOk()
            ->assertJsonPath('summary.auctions_with_sales', 0)
            ->assertJsonPath('summary.sold_items', 0)
            ->assertJsonPath('summary.revenue_after_tax', 0);
    }

    public function test_ended_summary_is_admin_only(): void
    {
        $this->actingAs($this->createUser())->getJson('/api/auctions/ended')->assertForbidden();
    }

    public function test_store_rejects_an_end_time_in_the_past(): void
    {
        $this
            ->actingAs($this->createAdmin())
            ->postJson('/api/auctions', [
                'title' => 'Late lot',
                'description' => 'Already over',
                'starting_price' => 10,
                'quantity' => 1,
                'max_per_bidder' => 1,
                'ends_at' => now()->subMinute()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_update_rejects_a_quantity_below_what_is_allocated_or_sold(): void
    {
        $auction = $this->createAuction(null, [
            'quantity' => 3,
            'max_per_bidder' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction);
        $this->createBid($auction);
        $this->createLeftoverPurchase($auction, null, ['quantity' => 1]);

        $this
            ->actingAs($this->createAdmin())
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction, ['quantity' => 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(3, (int) $this->reload($auction)->quantity);
    }

    public function test_update_rejects_a_starting_price_above_live_bids(): void
    {
        $auction = $this->createAuction(null, ['quantity' => 2, 'max_per_bidder' => 1]);
        $this->createBid($auction, null, ['amount' => '12.00']);
        $this->createBid($auction, null, ['amount' => '20.00']);

        $admin = $this->createAdmin();

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction, ['starting_price' => 12.01]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starting_price');

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction, ['starting_price' => 12]))
            ->assertOk();
    }

    public function test_update_resets_the_ending_soon_flag_when_the_end_time_changes(): void
    {
        $auction = $this->createAuction(null, ['ending_soon_notified' => true]);
        $admin = $this->createAdmin();

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction, ['title' => 'Renamed']))
            ->assertOk();

        $this->assertTrue($this->reload($auction)->ending_soon_notified);

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction, [
                'ends_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            ]))
            ->assertOk();

        $this->assertFalse($this->reload($auction)->ending_soon_notified);
    }

    public function test_update_returns_the_same_auction_shape_as_show(): void
    {
        $round = $this->createRound();
        $auction = $this->createAuction(null, ['auction_round_id' => $round->id]);
        $this->createQuestion($auction);
        $admin = $this->createAdmin();

        $show = $this->actingAs($admin)->getJson("/api/auctions/{$auction->id}")->assertOk();

        $this
            ->actingAs($admin)
            ->putJson("/api/auctions/{$auction->id}", $this->auctionPayload($auction))
            ->assertOk()
            ->assertJsonPath('auction.round.id', $round->id)
            ->assertJsonCount(1, 'auction.questions')
            ->assertJsonStructure(['auction' => array_keys((array) $show->json('auction'))]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function auctionPayload(\App\Models\Auction $auction, array $overrides = []): array
    {
        return array_merge([
            'title' => $auction->title,
            'description' => $auction->description,
            'starting_price' => (float) $auction->starting_price,
            'quantity' => (int) $auction->quantity,
            'max_per_bidder' => (int) $auction->max_per_bidder,
            'ends_at' => $auction->ends_at->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_index_counts_accepted_offers_and_reads_settings_once(): void
    {
        $settings = \App\Models\SiteSetting::instance();
        $settings->leftover_sales_enabled = true;
        $settings->save();

        $auction = $this->createAuction(null, [
            'quantity' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 2, 'status' => 'accepted']);
        $this->createLeftoverPriceOffer($auction, null, ['quantity' => 1, 'status' => 'pending']);
        $this->createAuction(null, ['quantity' => 2]);

        app()->forgetInstance(\App\Models\SiteSetting::class);
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->getJson('/api/auctions')->assertOk()->assertJsonPath('auctions.1.leftover_quantity', 1);

        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');
        $this->assertCount(
            1,
            $queries->filter(fn(mixed $sql) => is_string($sql) && str_contains($sql, 'from "site_settings"')),
        );
        $this->assertCount(
            0,
            $queries->filter(fn(mixed $sql) => is_string($sql) && str_starts_with($sql, 'select sum("quantity")')),
        );
    }
}
