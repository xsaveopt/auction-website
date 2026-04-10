<?php

namespace Tests\Concerns;

use App\Models\Announcement;
use App\Models\Auction;
use App\Models\AuctionImage;
use App\Models\AuctionQuestion;
use App\Models\AuctionRound;
use App\Models\Bid;
use App\Models\Category;
use App\Models\LeftoverPriceOffer;
use App\Models\LeftoverPurchase;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Str;

trait InteractsWithAuctionData
{
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function createAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function createCategory(array $attributes = []): Category
    {
        $name = $attributes['name'] ?? 'Category ' . Str::title(Str::random(6));

        return Category::query()->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'sort_order' => 0,
        ], $attributes));
    }

    protected function createRound(array $attributes = []): AuctionRound
    {
        return AuctionRound::query()->create(array_merge([
            'name' => 'Round ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ], $attributes));
    }

    protected function createAuction(?User $seller = null, array $attributes = []): Auction
    {
        $seller ??= $this->createUser();

        return Auction::query()->create(array_merge([
            'seller_id' => $seller->id,
            'title' => 'Auction ' . Str::title(Str::random(6)),
            'description' => 'Auction description',
            'starting_price' => '10.00',
            'quantity' => 1,
            'max_per_bidder' => 1,
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'category_id' => null,
        ], $attributes));
    }

    protected function createBid(Auction $auction, ?User $user = null, array $attributes = []): Bid
    {
        $user ??= $this->createUser();

        return $auction
            ->bids()
            ->create(array_merge([
                'user_id' => $user->id,
                'amount' => '15.00',
                'quantity' => 1,
            ], $attributes));
    }

    protected function createQuestion(Auction $auction, ?User $user = null, array $attributes = []): AuctionQuestion
    {
        $user ??= $this->createUser();

        return $auction
            ->questions()
            ->create(array_merge([
                'user_id' => $user->id,
                'question' => 'Is this still available?',
                'answer' => null,
                'answered_at' => null,
            ], $attributes));
    }

    protected function createLeftoverPriceOffer(
        Auction $auction,
        ?User $user = null,
        array $attributes = [],
    ): LeftoverPriceOffer {
        $user ??= $this->createUser();

        return $auction
            ->leftoverPriceOffers()
            ->create(array_merge([
                'user_id' => $user->id,
                'quantity' => 1,
                'offered_price_per_item' => '5.00',
                'status' => 'pending',
            ], $attributes));
    }

    protected function createLeftoverPurchase(
        Auction $auction,
        ?User $user = null,
        array $attributes = [],
    ): LeftoverPurchase {
        $user ??= $this->createUser();

        return $auction
            ->leftoverPurchases()
            ->create(array_merge([
                'user_id' => $user->id,
                'quantity' => 1,
                'price_per_item' => '7.50',
            ], $attributes));
    }

    protected function createAnnouncement(?User $author = null, array $attributes = []): Announcement
    {
        $author ??= $this->createAdmin();

        return Announcement::query()->create(array_merge([
            'author_id' => $author->id,
            'message' => 'Important auction update',
            'is_active' => true,
        ], $attributes));
    }

    protected function createImage(Auction $auction, array $attributes = []): AuctionImage
    {
        return $auction
            ->images()
            ->create(array_merge([
                'path' => "auctions/{$auction->id}/sample.jpg",
                'sort_order' => 1,
            ], $attributes));
    }

    protected function createPushSubscription(?User $user = null, array $attributes = []): PushSubscription
    {
        $user ??= $this->createUser();

        return PushSubscription::query()->create(array_merge([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/' . Str::lower(Str::random(8)),
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
        ], $attributes));
    }
}
