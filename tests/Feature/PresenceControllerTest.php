<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_heartbeat_returns_auction_updates(): void
    {
        $auction = $this->createAuction($this->createUser(), ['quantity' => 2]);
        $this->createBid($auction, $this->createUser(), [
            'amount' => '13.00',
            'quantity' => 1,
        ]);

        $this
            ->postJson('/api/presence/heartbeat', [
                'page_id' => 'home-page',
                'client_id' => 'client-1',
                'page_type' => 'home',
            ])
            ->assertOk()
            ->assertJsonPath('auction_updates.0.id', $auction->id)
            ->assertJsonPath('auction_ids.0', $auction->id);

        $this->assertDatabaseHas('presence_heartbeats', [
            'page_id' => 'home-page',
            'client_id' => 'client-1',
            'page_type' => 'home',
        ]);
    }

    public function test_auction_heartbeat_requires_an_auction_id(): void
    {
        $this
            ->postJson('/api/presence/heartbeat', [
                'page_id' => 'auction-page',
                'client_id' => 'client-2',
                'page_type' => 'auction',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('auction_id');
    }

    public function test_auction_heartbeat_returns_the_full_auction_payload(): void
    {
        $auction = $this->createAuction($this->createUser());
        $this->createBid($auction, $this->createUser(), ['amount' => '18.00']);

        $this
            ->postJson('/api/presence/heartbeat', [
                'page_id' => 'auction-page',
                'client_id' => 'client-3',
                'page_type' => 'auction',
                'auction_id' => $auction->id,
            ])
            ->assertOk()
            ->assertJsonPath('auction.id', $auction->id)
            ->assertJsonPath('auction.bids.0.amount', '18.00');
    }

    public function test_generic_page_heartbeat_returns_an_empty_payload_and_tracks_authenticated_users(): void
    {
        $user = $this->createUser();

        $this
            ->actingAs($user)
            ->postJson('/api/presence/heartbeat', [
                'page_id' => 'generic-page',
                'client_id' => 'client-4',
                'page_type' => 'page',
            ])
            ->assertOk()
            ->assertExactJson([]);

        $this->assertDatabaseHas('presence_heartbeats', [
            'page_id' => 'generic-page',
            'client_id' => 'client-4',
            'page_type' => 'page',
            'user_id' => $user->id,
        ]);
    }
}
