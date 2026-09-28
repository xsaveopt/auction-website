<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuctionImageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_upload_show_and_delete_images(): void
    {
        Storage::fake('public');

        $seller = $this->createAdmin();
        $auction = $this->createAuction($seller);

        $uploadResponse = $this->actingAs($seller)->post("/api/auctions/{$auction->id}/images", [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
            ],
        ]);

        $imageId = $uploadResponse->json('images.0.id');
        $this->assertIsInt($imageId);

        $uploadResponse->assertCreated();
        $this->assertDatabaseHas('auction_images', [
            'id' => $imageId,
            'auction_id' => $auction->id,
        ]);

        $showResponse = $this->get("/api/images/{$imageId}");

        $showResponse->assertOk();
        $this->assertStringContainsString('public', (string) $showResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', (string) $showResponse->headers->get('Cache-Control'));

        $this
            ->actingAs($seller)
            ->deleteJson("/api/images/{$imageId}")
            ->assertOk()
            ->assertJsonPath('message', 'Image deleted.');

        $this->assertDatabaseMissing('auction_images', ['id' => $imageId]);
    }

    public function test_any_admin_can_manage_auction_images(): void
    {
        Storage::fake('public');

        $seller = $this->createAdmin();
        $otherAdmin = $this->createAdmin();
        $auction = $this->createAuction($seller);

        $imageId = $this
            ->actingAs($otherAdmin)
            ->post("/api/auctions/{$auction->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('other-admin.jpg'),
                ],
            ])
            ->assertCreated()
            ->json('images.0.id');
        $this->assertIsInt($imageId);

        $this->actingAs($otherAdmin)->deleteJson("/api/images/{$imageId}")->assertOk();
        $this->assertDatabaseMissing('auction_images', ['id' => $imageId]);
    }

    public function test_non_admins_cannot_manage_auction_images(): void
    {
        Storage::fake('public');

        $auction = $this->createAuction($this->createAdmin());
        $image = $auction->images()->create(['path' => "auctions/{$auction->id}/kept.jpg", 'sort_order' => 1]);
        $user = $this->createUser();

        $this
            ->actingAs($user)
            ->post("/api/auctions/{$auction->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('blocked.jpg'),
                ],
            ])
            ->assertForbidden();

        $this->actingAs($user)->deleteJson("/api/images/{$image->id}")->assertForbidden();
    }
}
