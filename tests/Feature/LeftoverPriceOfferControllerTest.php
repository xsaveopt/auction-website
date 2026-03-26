<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeftoverPriceOfferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_offer_that_exhausts_stock_soft_deletes_other_pending_offers(): void
    {
        config(['auction.leftover_sales_enabled' => true]);

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

        // offer2 is soft-deleted because stock is gone
        $this->assertSoftDeleted('leftover_price_offers', ['id' => $offer2->id]);
    }

    public function test_accepting_offer_that_leaves_remaining_stock_keeps_other_pending_offers(): void
    {
        config(['auction.leftover_sales_enabled' => true]);

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
}
