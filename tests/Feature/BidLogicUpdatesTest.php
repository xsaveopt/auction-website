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
            \App\Http\Middleware\VerifyCsrfUnlessApiKey::class,
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

        $this->actingAs($user1)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15,
            'quantity' => 1,
        ])->assertCreated();

        $this->actingAs($user2)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15,
            'quantity' => 1,
        ])->assertCreated();

        $bids = $this->getJson("/api/auctions/{$auction->id}")->collect('auction.bids');

        $this->assertEquals(1, data_get($bids->firstWhere('user.id', $user1->id), 'won_quantity'));
        $this->assertEquals(0, data_get($bids->firstWhere('user.id', $user2->id), 'won_quantity'));
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

        $this->actingAs($bidder)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15,
            'quantity' => 5,
        ])->assertCreated();

        $this->actingAs($bidder)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15,
            'quantity' => 4,
        ])->assertUnprocessable()->assertJsonFragment([
            'message' => 'New bid must have a higher amount or a higher quantity than your current bid.',
        ]);

        $this->actingAs($bidder)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 20,
            'quantity' => 4,
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'You cannot lower your bid quantity, even with a higher amount.']);

        $this->actingAs($bidder)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 15,
            'quantity' => 6,
        ])->assertOk();
    }

    public function test_usernames_are_masked_for_public_and_other_users(): void
    {
        $seller = $this->createUser();
        $user1 = $this->createUser(['username' => 'real_user_1']);
        $user2 = $this->createUser(['username' => 'real_user_2']);
        $auction = $this->createAuction($seller);

        $this->actingAs($user1)->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 20,
            'quantity' => 1,
        ])->assertCreated();

        Auth::logout();

        $this->getJson("/api/auctions/{$auction->id}")->assertJsonPath('auction.bids.0.user.username', 'real_user_1');

        $this
            ->actingAs($user2)
            ->getJson("/api/auctions/{$auction->id}")
            ->assertJsonPath('auction.bids.0.user.username', 'real_user_1');

        $this
            ->actingAs($user1)
            ->getJson("/api/auctions/{$auction->id}")
            ->assertJsonPath('auction.bids.0.user.username', 'real_user_1');

        $this
            ->actingAs($this->createAdmin())
            ->getJson("/api/auctions/{$auction->id}")
            ->assertJsonPath('auction.bids.0.user.username', 'real_user_1');
    }
}
