<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Note: AuctionRound tests live in AuctionRoundControllerTest.php

class AdminControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_manual_bids_and_list_non_admin_users(): void
    {
        $admin = $this->createAdmin();
        $bidder = $this->createUser();
        $auction = $this->createAuction($this->createUser(), [
            'quantity' => 2,
            'max_per_bidder' => 2,
        ]);

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['username' => $bidder->username]);

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/bids", [
                'username' => $bidder->username,
                'amount' => 30,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('auction.bids.0.amount', '30.00');

        $bid = Bid::query()->where('auction_id', $auction->id)->firstOrFail();

        $this
            ->actingAs($admin)
            ->putJson("/api/admin/bids/{$bid->id}", [
                'amount' => 35,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('auction.bids.0.amount', '35.00');

        $this
            ->actingAs($admin)
            ->deleteJson("/api/admin/bids/{$bid->id}")
            ->assertOk();

        $this->assertSoftDeleted('bids', ['id' => $bid->id]);
    }

    public function test_admin_can_end_reactivate_extend_and_cancel_an_auction(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction($this->createUser(), [
            'status' => 'active',
            'ends_at' => now()->addHour(),
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/end")
            ->assertOk()
            ->assertJsonPath('auction.status', 'ended');

        $reactivatedEndsAt = now()->addDays(2)->toISOString();
        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/reactivate", [
                'ends_at' => $reactivatedEndsAt,
            ])
            ->assertOk()
            ->assertJsonPath('auction.status', 'active');

        $extendedEndsAt = now()->addDays(3)->toISOString();
        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/extend", [
                'ends_at' => $extendedEndsAt,
            ])
            ->assertOk();

        $this
            ->actingAs($admin)
            ->postJson("/api/admin/auctions/{$auction->id}/cancel")
            ->assertOk()
            ->assertJsonPath('auction.status', 'cancelled');
    }

    public function test_admin_can_view_paginated_audit_logs(): void
    {
        $admin = $this->createAdmin();

        AuditLog::record($admin, 'example.action');
        AuditLog::record($admin, 'second.action');

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/audit-log')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('logs.0.action', 'second.action');
    }

    public function test_bulk_update_assigns_auctions_to_any_round_including_ended(): void
    {
        $admin = $this->createAdmin();
        $round = $this->createRound(['status' => 'ended', 'ends_at' => now()->subDay()]);
        $auction1 = $this->createAuction();
        $auction2 = $this->createAuction();

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_round',
                'auction_ids' => [$auction1->id, $auction2->id],
                'round_id' => $round->id,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('auctions', ['id' => $auction1->id, 'auction_round_id' => $round->id]);
        $this->assertDatabaseHas('auctions', ['id' => $auction2->id, 'auction_round_id' => $round->id]);
    }

    public function test_bulk_update_unassigns_round_from_auctions(): void
    {
        $admin = $this->createAdmin();
        $round = $this->createRound();
        $auction = $this->createAuction(null, ['auction_round_id' => $round->id]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_round',
                'auction_ids' => [$auction->id],
                'round_id' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'auction_round_id' => null]);
    }

    public function test_bulk_update_assigns_category_to_auctions(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory(['name' => 'Electronics']);
        $auction1 = $this->createAuction();
        $auction2 = $this->createAuction();

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_category',
                'auction_ids' => [$auction1->id, $auction2->id],
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('auctions', ['id' => $auction1->id, 'category_id' => $category->id]);
        $this->assertDatabaseHas('auctions', ['id' => $auction2->id, 'category_id' => $category->id]);
    }

    public function test_bulk_update_clears_category_from_auctions(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $auction = $this->createAuction(null, ['category_id' => $category->id]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_category',
                'auction_ids' => [$auction->id],
                'category_id' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'category_id' => null]);
    }

    public function test_bulk_update_sets_location_on_auctions(): void
    {
        $admin = $this->createAdmin();
        $auction1 = $this->createAuction();
        $auction2 = $this->createAuction();

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_location',
                'auction_ids' => [$auction1->id, $auction2->id],
                'location' => 'Warehouse B',
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('auctions', ['id' => $auction1->id, 'location' => 'Warehouse B']);
        $this->assertDatabaseHas('auctions', ['id' => $auction2->id, 'location' => 'Warehouse B']);
    }

    public function test_bulk_update_clears_location_from_auctions(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['location' => 'Old Warehouse']);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_location',
                'auction_ids' => [$auction->id],
                'location' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('auctions', ['id' => $auction->id, 'location' => null]);
    }

    public function test_bulk_update_end_ends_active_auctions_and_skips_others(): void
    {
        $admin = $this->createAdmin();
        $active = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);
        $alreadyEnded = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'end',
                'auction_ids' => [$active->id, $alreadyEnded->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('auctions', ['id' => $active->id, 'status' => 'ended']);
        // Already-ended auction stays ended, was not re-processed
        $this->assertDatabaseHas('auctions', ['id' => $alreadyEnded->id, 'status' => 'ended']);
    }

    public function test_bulk_update_cancel_cancels_active_auctions_and_skips_others(): void
    {
        $admin = $this->createAdmin();
        $active = $this->createAuction(null, ['status' => 'active', 'ends_at' => now()->addHour()]);
        $alreadyEnded = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'cancel',
                'auction_ids' => [$active->id, $alreadyEnded->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('auctions', ['id' => $active->id, 'status' => 'cancelled']);
        // Ended auction is not cancelled
        $this->assertDatabaseHas('auctions', ['id' => $alreadyEnded->id, 'status' => 'ended']);
    }

    public function test_bulk_update_requires_admin(): void
    {
        $user = $this->createUser();
        $auction = $this->createAuction();

        $this
            ->actingAs($user)
            ->patchJson('/api/admin/auctions/bulk', [
                'action' => 'assign_location',
                'auction_ids' => [$auction->id],
                'location' => 'Somewhere',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_bulk_comment_own_audit_log_entries(): void
    {
        $admin = $this->createAdmin();
        $log1 = AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'test.one', 'created_at' => now()]);
        $log2 = AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'test.two', 'created_at' => now()]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/audit-log/bulk-comment', [
                'ids' => [$log1->id, $log2->id],
                'comment' => 'Reviewed and approved',
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('audit_logs', ['id' => $log1->id, 'comment' => 'Reviewed and approved']);
        $this->assertDatabaseHas('audit_logs', ['id' => $log2->id, 'comment' => 'Reviewed and approved']);
    }

    public function test_bulk_comment_does_not_update_another_admins_entries(): void
    {
        $admin1 = $this->createAdmin();
        $admin2 = $this->createAdmin();
        $log = AuditLog::query()->create(['user_id' => $admin2->id, 'action' => 'other.action', 'created_at' => now()]);

        $this
            ->actingAs($admin1)
            ->patchJson('/api/admin/audit-log/bulk-comment', [
                'ids' => [$log->id],
                'comment' => 'Should not apply',
            ])
            ->assertOk()
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'comment' => null]);
    }

    public function test_bulk_comment_can_clear_existing_comments(): void
    {
        $admin = $this->createAdmin();
        $log = AuditLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'test.action',
            'comment' => 'Old comment',
            'created_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->patchJson('/api/admin/audit-log/bulk-comment', [
                'ids' => [$log->id],
                'comment' => null,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'comment' => null]);
    }
}
