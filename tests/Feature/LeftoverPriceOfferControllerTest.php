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

        // Accept offer1 — exhausts the only leftover item
        $this
            ->actingAs($admin)
            ->postJson("/api/admin/leftover-price-offers/{$offer1->id}/accept")
            ->assertOk();

        // offer1 is accepted (not deleted)
        $this->assertDatabaseHas('leftover_price_offers', [
            'id' => $offer1->id,
            'status' => 'accepted',
        ]);
        $this->assertNotSoftDeleted('leftover_price_offers', ['id' => $offer1->id]);

        // offer2 is rejected because stock is gone
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

        // Accept offer1 — one item remains
        $this
            ->actingAs($admin)
            ->postJson("/api/admin/leftover-price-offers/{$offer1->id}/accept")
            ->assertOk();

        // offer2 should still be pending and not deleted
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

        $this
            ->actingAs($this->createUser())
            ->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
                'quantity' => 1,
                'offered_price_per_item' => 5.00,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', "This auction's round has been closed.");
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

        $this
            ->actingAs($this->createUser())
            ->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
                'quantity' => 1,
                'offered_price_per_item' => 5.00,
            ])
            ->assertCreated();
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

        $this
            ->actingAs($user)
            ->postJson("/api/auctions/{$auction->id}/leftover-price-offers", [
                'quantity' => 1,
                'offered_price_per_item' => 6.50,
            ])
            ->assertCreated()
            ->assertJsonPath('auction.leftover_price_offers.0.user.username', $user->username);

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
            LeftoverPriceOffer::withTrashed()
                ->where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->count(),
        );
    }
}
