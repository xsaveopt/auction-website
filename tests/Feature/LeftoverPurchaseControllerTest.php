<?php

namespace Tests\Feature;

use App\Models\LeftoverPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeftoverPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_buy_leftover_items_once_when_sales_are_enabled(): void
    {
        config(['auction.leftover_sales_enabled' => true]);

        $seller = $this->createUser();
        $buyer = $this->createUser();
        $auction = $this->createAuction($seller, [
            'starting_price' => '10.00',
            'quantity' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, $this->createUser(), [
            'amount' => '12.00',
            'quantity' => 1,
        ]);

        $this
            ->actingAs($buyer)
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('auction.leftover_purchases.0.quantity', 2);

        $this
            ->actingAs($buyer)
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You have already purchased leftover items from this auction.');

        $this->assertDatabaseHas('leftover_purchases', [
            'auction_id' => $auction->id,
            'user_id' => $buyer->id,
            'quantity' => 2,
            'price_per_item' => '7.50',
        ]);
    }

    public function test_admin_leftover_purchases_merge_for_the_same_buyer_and_can_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $auction = $this->createAuction($seller, [
            'starting_price' => '20.00',
            'quantity' => 4,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/leftover-purchases", [
                'username' => $buyer->username,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/leftover-purchases", [
                'username' => $buyer->username,
                'quantity' => 2,
            ])
            ->assertCreated();

        $purchase = LeftoverPurchase::query()->where('auction_id', $auction->id)->firstOrFail();

        $this->assertSame(3, $purchase->quantity);
        $this->assertSame(15.0, (float) $purchase->price_per_item);

        $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/leftover-purchases/{$purchase->id}")
            ->assertOk();

        $this->assertSoftDeleted('leftover_purchases', ['id' => $purchase->id]);
    }

    public function test_leftover_purchases_are_rejected_when_sales_are_disabled_or_the_auction_is_still_active(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $auction = $this->createAuction($seller);

        $this
            ->actingAs($buyer)
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Leftover sales are not enabled.');

        config(['auction.leftover_sales_enabled' => true]);

        $this
            ->actingAs($buyer)
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This auction is still active.');
    }

    public function test_leftover_purchases_reject_the_seller_or_fully_allocated_auctions(): void
    {
        config(['auction.leftover_sales_enabled' => true]);

        $seller = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, $this->createUser(), [
            'amount' => '30.00',
            'quantity' => 1,
        ]);

        $this
            ->actingAs($seller)
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot purchase from your own auction.');

        $this
            ->actingAs($this->createUser())
            ->postJson("/api/auctions/{$auction->id}/leftover-purchases", [
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No leftover items are available.');
    }

    public function test_admin_leftover_purchases_cannot_exceed_available_quantity(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $auction = $this->createAuction($this->createUser(), [
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($auction, $this->createUser(), [
            'amount' => '18.00',
            'quantity' => 1,
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/leftover-purchases", [
                'username' => $buyer->username,
                'quantity' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only 1 item(s) available.');
    }
}
