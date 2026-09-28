<?php

namespace Tests\Feature;

use App\Models\LeftoverPurchase;
use App\Models\OverrideSale;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverrideSaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sells_items_from_several_closed_auctions_in_one_override_sale(): void
    {
        $siteSettings = SiteSetting::instance();
        $siteSettings->leftover_sales_enabled = false;
        $siteSettings->save();

        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $round = $this->createRound(['status' => 'ended']);
        $chairs = $this->createAuction(null, [
            'title' => 'Chair',
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
            'auction_round_id' => $round->id,
        ]);
        $desks = $this->createAuction(null, [
            'title' => 'Desk',
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
            'auction_round_id' => $round->id,
        ]);
        $existing = $this->createLeftoverPurchase($chairs, $buyer, ['quantity' => 1, 'price_per_item' => '15.00']);

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', [
                'username' => $buyer->username,
                'items' => [
                    ['auction_id' => $chairs->id, 'quantity' => 3, 'price_per_item' => '12.50'],
                    ['auction_id' => $desks->id, 'quantity' => 2, 'price_per_item' => '40'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('override_sale.user.username', $buyer->username)
            ->assertJsonPath('override_sale.total', '117.50')
            ->assertJsonPath('override_sale.items.0.auction_title', 'Chair')
            ->assertJsonPath('override_sale.items.1.total', '80.00');

        $sale = OverrideSale::query()->sole();
        $this->assertSame($sale->id, $response->json('override_sale.id'));
        $this->assertSame($admin->id, $sale->created_by);
        $this->assertSame(2, $sale->purchases()->count());
        $this->assertSame(1, $this->reload($existing)->quantity);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'override_sale.create',
            'target_id' => $sale->id,
        ]);

        $this->actingAs($admin)->getJson("/api/auctions/{$chairs->id}")->assertJsonPath('auction.leftover_quantity', 1);

        $this
            ->actingAs($admin)
            ->getJson("/api/auctions/leftovers?round_id={$round->id}")
            ->assertOk()
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.id', $chairs->id);

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/override-sales')
            ->assertOk()
            ->assertJsonCount(1, 'override_sales')
            ->assertJsonPath('override_sales.0.id', $sale->id)
            ->assertJsonCount(2, 'override_sales.0.items');
    }

    public function test_override_sale_is_rolled_back_when_any_item_is_invalid(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $ended = $this->createAuction(null, [
            'title' => 'Lamp',
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($ended, null, ['quantity' => 1]);
        $active = $this->createAuction(null, ['title' => 'Sofa', 'quantity' => 3]);
        $cancelled = $this->createAuction(null, [
            'title' => 'Rug',
            'status' => 'cancelled',
            'ends_at' => now()->subHour(),
        ]);
        $soldOut = $this->createAuction(null, [
            'title' => 'Box',
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($soldOut, null, ['quantity' => 1]);
        $good = ['auction_id' => $ended->id, 'quantity' => 1, 'price_per_item' => '5.00'];

        $cases = [
            [['auction_id' => $active->id, 'quantity' => 1, 'price_per_item' => '5.00'], 'Sofa is still active.'],
            [['auction_id' => $cancelled->id, 'quantity' => 1, 'price_per_item' => '5.00'], 'Rug was cancelled.'],
            [
                ['auction_id' => $soldOut->id, 'quantity' => 1, 'price_per_item' => '5.00'],
                'Only 0 item(s) of Box available.',
            ],
        ];

        foreach ($cases as [$item, $message]) {
            $this
                ->actingAs($admin)
                ->postJson('/api/admin/override-sales', [
                    'username' => $buyer->username,
                    'items' => [$good, $item],
                ])
                ->assertUnprocessable()
                ->assertJsonPath('message', $message);
        }

        $this->assertSame(0, OverrideSale::withTrashed()->count());
        $this->assertSame(0, LeftoverPurchase::withTrashed()->count());

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', [
                'username' => $buyer->username,
                'items' => [['auction_id' => $ended->id, 'quantity' => 1, 'price_per_item' => '5.001']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.price_per_item');

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', ['username' => $buyer->username, 'items' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_deleting_an_override_sale_returns_its_items_to_the_leftovers(): void
    {
        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, [
            'quantity' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);

        $this
            ->actingAs($admin)
            ->postJson('/api/admin/override-sales', [
                'username' => $buyer->username,
                'items' => [['auction_id' => $auction->id, 'quantity' => 2, 'price_per_item' => '3.00']],
            ])
            ->assertCreated();
        $saleId = OverrideSale::query()->sole()->id;

        $this->actingAs($admin)->getJson('/api/auctions/leftovers')->assertJsonCount(0, 'auctions');

        $this->actingAs($admin)->deleteJson("/api/admin/override-sales/{$saleId}")->assertOk();

        $this->assertSoftDeleted('override_sales', ['id' => $saleId]);
        $this->assertSoftDeleted('leftover_purchases', ['override_sale_id' => $saleId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'override_sale.delete', 'target_id' => $saleId]);
        $this
            ->actingAs($admin)
            ->getJson('/api/auctions/leftovers')
            ->assertJsonCount(1, 'auctions')
            ->assertJsonPath('auctions.0.leftover_quantity', 2);
    }
}
