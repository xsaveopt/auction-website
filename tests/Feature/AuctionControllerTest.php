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

    public function test_destroy_soft_deletes_the_auction_and_removes_public_images(): void
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
        Storage::disk('public')->assertMissing($path);
    }
}
