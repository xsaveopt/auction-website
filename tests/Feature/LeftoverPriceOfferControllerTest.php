<?php

namespace Tests\Feature;

use App\Models\LeftoverPriceOffer;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeftoverPriceOfferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_offer_that_exhausts_stock_rejects_other_pending_offers(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = true;
        $siteSettings->save();

        $admin = $this->createAdmin();
        $offerUser1 = $this->createUser();
        $offerUser2 = $this->createUser();
        $auction = $this->createAuction($this->createUser(), [
            'starting_price' => '10.00',
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $offer1 = $this->createLeftoverPriceOffer($auction, $offerUser1, [
            'quantity' => 1,
            'offered_price_per_item' => '6.00',
        ]);
        $offer2 = $this->createLeftoverPriceOffer($auction, $offerUser2, [
            'quantity' => 1,
            'offered_price_per_item' => '5.00',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/leftover-price-offers/{$offer1->id}/accept")->assertOk();

        $this->assertDatabaseHas('leftover_price_offers', [
            'id' => $offer1->id,
            'status' => 'accepted',
        ]);
        $this->assertNotSoftDeleted('leftover_price_offers', ['id' => $offer1->id]);

        $this->assertDatabaseHas('leftover_price_offers', ['id' => $offer2->id, 'status' => 'rejected']);
    }

    public function test_accepting_offer_that_leaves_remaining_stock_keeps_other_pending_offers(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = true;
        $siteSettings->save();

        $admin = $this->createAdmin();
        $offerUser1 = $this->createUser();
        $offerUser2 = $this->createUser();
        $auction = $this->createAuction($this->createUser(), [
            'starting_price' => '10.00',
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $offer1 = $this->createLeftoverPriceOffer($auction, $offerUser1, [
            'quantity' => 1,
            'offered_price_per_item' => '6.00',
        ]);
        $offer2 = $this->createLeftoverPriceOffer($auction, $offerUser2, [
            'quantity' => 1,
            'offered_price_per_item' => '5.00',
        ]);

        $this->actingAs($admin)->postJson("/api/admin/leftover-price-offers/{$offer1->id}/accept")->assertOk();

        $this->assertNotSoftDeleted('leftover_price_offers', ['id' => $offer2->id]);
        $this->assertDatabaseHas('leftover_price_offers', [
            'id' => $offer2->id,
            'status' => 'pending',
        ]);
    }

    public function test_price_offer_rejected_when_auction_round_is_closed(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = true;
        $siteSettings->save();

        $round = $this->createRound(['status' => 'ended', 'ends_at' => now()->subDay()]);
        $auction = $this->createAuction(null, [
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
            'auction_round_id' => $round->id,
        ]);

        $this->actingAs($this->createUser())->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
            'quantity' => 1,
            'offered_price_per_item' => 5.00,
        ])->assertUnprocessable()->assertJsonPath('message', "This auction's round has been closed.");
    }

    public function test_price_offer_allowed_when_auction_has_no_round(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = true;
        $siteSettings->save();

        $auction = $this->createAuction(null, [
            'starting_price' => '10.00',
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $this->actingAs($this->createUser())->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
            'quantity' => 1,
            'offered_price_per_item' => 5.00,
        ])->assertCreated();
    }

    public function test_user_can_submit_a_new_offer_after_a_soft_deleted_offer(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = true;
        $siteSettings->save();

        $user = $this->createUser();
        $auction = $this->createAuction($this->createUser(), [
            'starting_price' => '10.00',
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $oldOffer = $this->createLeftoverPriceOffer($auction, $user, [
            'offered_price_per_item' => '6.00',
        ]);
        $oldOffer->delete();

        $this->actingAs($user)->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
            'quantity' => 1,
            'offered_price_per_item' => 6.50,
        ])->assertCreated()->assertJsonPath('auction.leftover_price_offers.0.user.username', $user->username);

        $this->assertSoftDeleted('leftover_price_offers', ['id' => $oldOffer->id]);
        $this->assertDatabaseHas('leftover_price_offers', [
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'offered_price_per_item' => '6.50',
            'status' => 'pending',
            'deleted_at' => null,
        ]);
        $this->assertSame(
            2,
            LeftoverPriceOffer::withTrashed()->where('auction_id', $auction->id)->where('user_id', $user->id)->count(),
        );
    }

    public function test_admin_index_lists_pending_offers_with_leftover_price_and_round_filter(): void
    {
        $round = $this->createRound(['status' => 'active']);
        $inRound = $this->createAuction(null, [
            'starting_price' => '20.00',
            'status' => 'ended',
            'ends_at' => now()->subHour(),
            'auction_round_id' => $round->id,
        ]);
        $this->createImage($inRound);
        $outsideRound = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $buyer = $this->createUser();
        $pending = $this->createLeftoverPriceOffer($inRound, $buyer, ['offered_price_per_item' => '9.00']);
        $this->createLeftoverPriceOffer($inRound, null, ['status' => 'accepted']);
        $this->createLeftoverPriceOffer($outsideRound);

        $admin = $this->createAdmin();

        $this->actingAs($admin)->getJson('/api/admin/leftover-price-offers')->assertOk()->assertJsonCount(2, 'offers');

        $this
            ->actingAs($admin)
            ->getJson("/api/admin/leftover-price-offers?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.id', $pending->id)
            ->assertJsonPath('offers.0.status', 'pending')
            ->assertJsonPath('offers.0.user.username', $buyer->username)
            ->assertJsonPath('offers.0.auction.id', $inRound->id)
            ->assertJsonPath('offers.0.auction.leftover_price', '15.00')
            ->assertJsonCount(1, 'offers.0.auction.images');
    }

    public function test_admin_can_request_a_rebid_for_tied_offers(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer1 = $this->createLeftoverPriceOffer($auction, null, ['offered_price_per_item' => '6.00']);
        $offer2 = $this->createLeftoverPriceOffer($auction, null, ['offered_price_per_item' => '6.00']);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', ['offer_ids' => [$offer1->id, $offer2->id]])
            ->assertOk()
            ->assertJsonPath('offer_ids', [$offer1->id, $offer2->id]);

        $this->assertNotNull($offer1->fresh()?->rebid_requested_at);
        $this->assertNotNull($offer2->fresh()?->rebid_requested_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'leftover_price_offer.rebid_requested',
        ]);
    }

    public function test_request_rebid_rejects_invalid_offer_sets(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $otherAuction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $a = $this->createLeftoverPriceOffer($auction, null, ['offered_price_per_item' => '6.00']);
        $b = $this->createLeftoverPriceOffer($auction, null, ['offered_price_per_item' => '7.00']);
        $c = $this->createLeftoverPriceOffer($otherAuction, null, ['offered_price_per_item' => '6.00']);
        $accepted = $this->createLeftoverPriceOffer($auction, null, [
            'offered_price_per_item' => '6.00',
            'status' => 'accepted',
        ]);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', [
                'offer_ids' => [$a->id],
            ])
            ->assertJsonValidationErrors(['offer_ids']);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', [
                'offer_ids' => [$a->id, $accepted->id],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Some offers are invalid or no longer pending.');

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', [
                'offer_ids' => [$a->id, $c->id],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'All offers must belong to the same auction.');

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', [
                'offer_ids' => [$a->id, $b->id],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'All offers must have the same price to request a rebid.');

        $this->assertSame(0, LeftoverPriceOffer::query()->whereNotNull('rebid_requested_at')->count());
    }

    public function test_admin_can_reject_a_pending_offer(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction);

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/leftover-price-offers/{$offer->id}/reject")
            ->assertOk()
            ->assertJsonPath('auction.id', $auction->id);

        $this->assertDatabaseHas('leftover_price_offers', ['id' => $offer->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'leftover_price_offer.reject',
            'target_id' => $offer->id,
        ]);
    }

    public function test_rejecting_an_offer_on_a_deleted_auction_returns_a_message(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction);
        $auction->delete();

        $this
            ->actingAs($this->createAdmin())
            ->postJson("/api/admin/leftover-price-offers/{$offer->id}/reject")
            ->assertOk()
            ->assertJsonPath('message', 'Offer rejected.');

        $this->assertDatabaseHas('leftover_price_offers', ['id' => $offer->id, 'status' => 'rejected']);
    }

    public function test_rejecting_a_non_pending_offer_is_refused(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction, null, ['status' => 'accepted']);

        $this
            ->actingAs($this->createAdmin())
            ->postJson("/api/admin/leftover-price-offers/{$offer->id}/reject")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This offer is no longer pending.');

        $this->assertDatabaseHas('leftover_price_offers', ['id' => $offer->id, 'status' => 'accepted']);
    }

    public function test_admin_can_create_an_offer_on_behalf_of_a_user(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, ['quantity' => 3, 'status' => 'ended', 'ends_at' => now()->subHour()]);

        $this->actingAs($admin)->postJson("/api/admin/auctions/{$auction->id}/leftover-price-offers", [
            'username' => $buyer->username,
            'quantity' => 2,
            'offered_price_per_item' => 4.5,
        ])->assertCreated()->assertJsonPath('auction.id', $auction->id);

        $this->assertDatabaseHas('leftover_price_offers', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'leftover_price_offer.create',
        ]);
    }

    public function test_admin_store_updates_an_existing_offer_for_the_same_user(): void
    {
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, ['quantity' => 3, 'status' => 'ended', 'ends_at' => now()->subHour()]);
        $existing = $this->createLeftoverPriceOffer($auction, $buyer, [
            'quantity' => 1,
            'offered_price_per_item' => '3.00',
            'status' => 'rejected',
        ]);

        $this->actingAs($this->createAdmin())->postJson("/api/admin/auctions/{$auction->id}/leftover-price-offers", [
            'username' => $buyer->username,
            'quantity' => 3,
            'offered_price_per_item' => 5,
        ])->assertCreated();

        $this->assertSame(1, LeftoverPriceOffer::query()->where('auction_id', $auction->id)->count());
        $existing->refresh();
        $this->assertSame(3, (int) $existing->quantity);
        $this->assertSame('pending', $existing->status);
    }

    public function test_admin_store_rejects_unavailable_quantities_and_unknown_users(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, ['quantity' => 2, 'status' => 'ended', 'ends_at' => now()->subHour()]);
        $soldOut = $this->createAuction(null, ['quantity' => 1, 'status' => 'ended', 'ends_at' => now()->subHour()]);
        $this->createBid($soldOut, null, ['quantity' => 1]);

        $this->actingAs($admin)->postJson("/api/admin/auctions/{$auction->id}/leftover-price-offers", [
            'username' => 'nobody-here',
            'quantity' => 1,
            'offered_price_per_item' => 5,
        ])->assertJsonValidationErrors(['username']);

        $this->actingAs($admin)->postJson("/api/admin/auctions/{$auction->id}/leftover-price-offers", [
            'username' => $buyer->username,
            'quantity' => 3,
            'offered_price_per_item' => 5,
        ])->assertUnprocessable()->assertJsonPath('message', 'Only 2 item(s) available.');

        $this->actingAs($admin)->postJson("/api/admin/auctions/{$soldOut->id}/leftover-price-offers", [
            'username' => $buyer->username,
            'quantity' => 1,
            'offered_price_per_item' => 5,
        ])->assertUnprocessable()->assertJsonPath('message', 'No leftover items are available.');

        $this->assertDatabaseCount('leftover_price_offers', 0);
    }

    public function test_admin_can_delete_an_offer(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction);

        $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/leftover-price-offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('auction.id', $auction->id);

        $this->assertSoftDeleted('leftover_price_offers', ['id' => $offer->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'leftover_price_offer.delete',
            'target_id' => $offer->id,
        ]);
    }

    public function test_deleting_an_offer_on_a_deleted_auction_returns_a_message(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction);
        $auction->delete();

        $this
            ->actingAs($this->createAdmin())
            ->deleteJson("/api/admin/leftover-price-offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Offer deleted.');

        $this->assertSoftDeleted('leftover_price_offers', ['id' => $offer->id]);
    }

    public function test_admin_offer_routes_are_admin_only(): void
    {
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $offer = $this->createLeftoverPriceOffer($auction);
        $user = $this->createUser();

        $this->actingAs($user)->getJson('/api/admin/leftover-price-offers')->assertForbidden();
        $this
            ->actingAs($user)
            ->postJson('/api/admin/leftover-price-offers/request-rebid', [
                'offer_ids' => [$offer->id, $offer->id],
            ])
            ->assertForbidden();
        $this->actingAs($user)->postJson("/api/admin/leftover-price-offers/{$offer->id}/reject")->assertForbidden();
        $this->actingAs($user)->postJson("/api/admin/auctions/{$auction->id}/leftover-price-offers", [
            'username' => $user->username,
            'quantity' => 1,
            'offered_price_per_item' => 5,
        ])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/admin/leftover-price-offers/{$offer->id}")->assertForbidden();

        $this->assertDatabaseHas('leftover_price_offers', [
            'id' => $offer->id,
            'status' => 'pending',
            'deleted_at' => null,
        ]);
    }
}
