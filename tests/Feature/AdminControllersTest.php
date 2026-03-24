<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

        $this->actingAs($admin)->deleteJson("/api/admin/bids/{$bid->id}")->assertOk();

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
}
