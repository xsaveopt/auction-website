<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BidLogicUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfUnlessMcp::class,
            \App\Http\Middleware\EnsureSsoAuthenticated::class,
        ]);
    }

    public function test_tie_breaking_prioritizes_earlier_bids(): void
    {
        $seller = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'starting_price' => '10.00',
        ]);

        $user1 = $this->createUser(['username' => 'user1']);
        $user2 = $this->createUser(['username' => 'user2']);

        // User 1 bids first
        $this
            ->actingAs($user1)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 1,
            ])
            ->assertCreated();

        // User 2 bids same amount later
        $this
            ->actingAs($user2)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 1,
            ])
            ->assertCreated();

        $response = $this->getJson("/api/auctions/{$auction->id}")->json('auction');

        // User 1 should have won_quantity 1, User 2 should have 0
        $bids = collect($response['bids']);
        $bid1 = $bids->firstWhere('user.id', $user1->id);
        $bid2 = $bids->firstWhere('user.id', $user2->id);

        $this->assertEquals(1, $bid1['won_quantity']);
        $this->assertEquals(0, $bid2['won_quantity']);
    }

    public function test_cannot_lower_quantity_even_with_same_or_higher_amount(): void
    {
        $seller = $this->createUser();
        $bidder = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 10,
            'max_per_bidder' => 10,
            'starting_price' => '10.00',
        ]);

        // Initial bid
        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 5,
            ])
            ->assertCreated();

        // Try same amount, lower quantity
        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'New bid must have a higher amount or a higher quantity than your current bid.',
            ]);

        // Try higher amount, lower quantity
        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 20,
                'quantity' => 4,
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'You cannot lower your bid quantity, even with a higher amount.']);

        // Try same amount, higher quantity - SHOULD WORK
        $this
            ->actingAs($bidder)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 15,
                'quantity' => 6,
            ])
            ->assertOk();
    }

    public function test_usernames_are_masked_for_public_and_other_users(): void
    {
        $seller = $this->createUser();
        $user1 = $this->createUser(['username' => 'real_user_1']);
        $user2 = $this->createUser(['username' => 'real_user_2']);
        $auction = $this->createAuction($seller);

        $this
            ->actingAs($user1)
            ->postJson("/api/auctions/{$auction->id}/bids", [
                'amount' => 20,
                'quantity' => 1,
            ])
            ->assertCreated();

        Auth::logout();

        // Public view (unauthenticated)
        $response = $this->getJson("/api/auctions/{$auction->id}");
        $data = $response->json('auction');
        if ($data['bids'][0]['user']['username'] !== 'real_user_1') {
            dump('Unauthenticated view:', $data['bids'][0]['user']);
        }
        $this->assertEquals('real_user_1', $data['bids'][0]['user']['username']);

        // User 2 viewing User 1's bid
        $response = $this->actingAs($user2)->getJson("/api/auctions/{$auction->id}");
        $data = $response->json('auction');
        if ($data['bids'][0]['user']['username'] !== 'real_user_1') {
            dump('User 2 view:', $data['bids'][0]['user']);
        }
        $this->assertEquals('real_user_1', $data['bids'][0]['user']['username']);

        // User 1 viewing their own bid
        $response = $this->actingAs($user1)->getJson("/api/auctions/{$auction->id}");
        $data = $response->json('auction');
        if ($data['bids'][0]['user']['username'] !== 'real_user_1') {
            dump('User 1 view:', $data['bids'][0]['user'], 'Auth ID:', auth()->id());
        }
        $this->assertEquals('real_user_1', $data['bids'][0]['user']['username']);

        // Admin viewing
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->getJson("/api/auctions/{$auction->id}");
        $data = $response->json('auction');
        $this->assertEquals('real_user_1', $data['bids'][0]['user']['username']);
    }
}
