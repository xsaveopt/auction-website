<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_are_blocked_from_all_authenticated_routes(): void
    {
        $auction = $this->createAuction($this->createAdmin(), ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $question = $this->createQuestion($auction);
        $announcement = $this->createAnnouncement();
        $image = $this->createImage($auction);
        $bid = $this->createBid($auction);
        $purchase = $this->createLeftoverPurchase($auction);

        $cases = [
            ['method' => 'postJson', 'uri' => '/api/logout'],
            ['method' => 'getJson', 'uri' => '/api/my-auctions'],
            ['method' => 'postJson', 'uri' => "/api/auctions/{$auction->id}/bids"],
            ['method' => 'postJson', 'uri' => "/api/auctions/{$auction->id}/leftover-purchases"],
            ['method' => 'postJson', 'uri' => "/api/auctions/{$auction->id}/questions"],
            ['method' => 'putJson', 'uri' => "/api/questions/{$question->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/questions/{$question->id}"],
            ['method' => 'getJson', 'uri' => '/api/push/config'],
            ['method' => 'putJson', 'uri' => '/api/push/subscription'],
            ['method' => 'deleteJson', 'uri' => '/api/push/subscription'],
            ['method' => 'postJson', 'uri' => '/api/announcement'],
            ['method' => 'deleteJson', 'uri' => "/api/announcements/{$announcement->id}"],
            ['method' => 'postJson', 'uri' => '/api/categories'],
            ['method' => 'putJson', 'uri' => '/api/categories/1'],
            ['method' => 'deleteJson', 'uri' => '/api/categories/1'],
            ['method' => 'postJson', 'uri' => '/api/auctions'],
            ['method' => 'putJson', 'uri' => "/api/auctions/{$auction->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/auctions/{$auction->id}"],
            ['method' => 'getJson', 'uri' => '/api/auctions/ended'],
            ['method' => 'get', 'uri' => "/api/auctions/{$auction->id}/quotes/{$bid->id}", 'status' => 302],
            ['method' => 'get', 'uri' => '/api/quotes/example.pdf', 'status' => 302],
            ['method' => 'post', 'uri' => "/api/auctions/{$auction->id}/images", 'status' => 302],
            ['method' => 'deleteJson', 'uri' => "/api/images/{$image->id}"],
            ['method' => 'getJson', 'uri' => '/api/questions'],
            ['method' => 'getJson', 'uri' => '/api/admin/users'],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/end"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/cancel"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/reactivate"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/extend"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/bids"],
            ['method' => 'putJson', 'uri' => "/api/admin/bids/{$bid->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/admin/bids/{$bid->id}"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/leftover-purchases"],
            ['method' => 'deleteJson', 'uri' => "/api/admin/leftover-purchases/{$purchase->id}"],
            ['method' => 'getJson', 'uri' => '/api/admin/audit-log'],
        ];

        foreach ($cases as $case) {
            $this->{$case['method']}($case['uri'])->assertStatus($case['status'] ?? 401);
        }
    }

    public function test_non_admin_users_are_blocked_from_admin_routes(): void
    {
        $user = $this->createUser();
        $auction = $this->createAuction($this->createAdmin(), ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $activeAuction = $this->createAuction($this->createAdmin());
        $announcement = $this->createAnnouncement();
        $category = $this->createCategory();
        $image = $this->createImage($auction);
        $question = $this->createQuestion($auction);
        $bid = $this->createBid($auction);
        $purchase = $this->createLeftoverPurchase($auction);

        $cases = [
            ['method' => 'postJson', 'uri' => '/api/announcement'],
            ['method' => 'deleteJson', 'uri' => "/api/announcements/{$announcement->id}"],
            ['method' => 'postJson', 'uri' => '/api/categories'],
            ['method' => 'putJson', 'uri' => "/api/categories/{$category->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/categories/{$category->id}"],
            ['method' => 'postJson', 'uri' => '/api/auctions'],
            ['method' => 'putJson', 'uri' => "/api/auctions/{$auction->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/auctions/{$auction->id}"],
            ['method' => 'getJson', 'uri' => '/api/auctions/ended'],
            ['method' => 'get', 'uri' => "/api/auctions/{$auction->id}/quotes/{$bid->id}"],
            ['method' => 'get', 'uri' => '/api/quotes/example.pdf'],
            ['method' => 'post', 'uri' => "/api/auctions/{$auction->id}/images"],
            ['method' => 'deleteJson', 'uri' => "/api/images/{$image->id}"],
            ['method' => 'getJson', 'uri' => '/api/questions'],
            ['method' => 'getJson', 'uri' => '/api/admin/users'],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/end"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/cancel"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/reactivate"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/extend"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/bids"],
            ['method' => 'putJson', 'uri' => "/api/admin/bids/{$bid->id}"],
            ['method' => 'deleteJson', 'uri' => "/api/admin/bids/{$bid->id}"],
            ['method' => 'postJson', 'uri' => "/api/admin/auctions/{$auction->id}/leftover-purchases"],
            ['method' => 'deleteJson', 'uri' => "/api/admin/leftover-purchases/{$purchase->id}"],
            ['method' => 'getJson', 'uri' => '/api/admin/audit-log'],
        ];

        foreach ($cases as $case) {
            $this->actingAs($user)->{$case['method']}($case['uri'])->assertForbidden();
        }

        $this->actingAs($user)->getJson('/api/announcement')->assertOk();
        $this->actingAs($user)->getJson('/api/categories')->assertOk();
        $this->actingAs($user)->getJson("/api/auctions/{$auction->id}")->assertOk();
        $this->actingAs($user)->getJson('/api/auctions')->assertOk();
        $this
            ->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
        $this->actingAs($user)->getJson('/api/push/config')->assertOk();
        $this->actingAs($user)->getJson('/api/my-auctions')->assertOk();
        $this
            ->actingAs($user)
            ->postJson("/api/auctions/{$activeAuction->id}/questions", [
                'question' => 'Allowed question',
            ])
            ->assertCreated();
        $this
            ->actingAs($user)
            ->postJson("/api/auctions/{$activeAuction->id}/bids", [
                'amount' => 20,
                'quantity' => 1,
            ])
            ->assertCreated();
        $this
            ->actingAs($user)
            ->postJson('/api/presence/heartbeat', [
                'page_id' => 'matrix-home',
                'client_id' => 'matrix-client',
                'page_type' => 'page',
            ])
            ->assertOk();
    }
}
