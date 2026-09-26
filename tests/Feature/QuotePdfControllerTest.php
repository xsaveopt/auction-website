<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class QuotePdfControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_a_quote_for_a_winning_bid(): void
    {
        $admin = $this->createAdmin();
        $seller = $this->createUser();
        $winner = $this->createUser();
        $auction = $this->createAuction($seller, [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $bid = $this->createBid($auction, $winner, [
            'amount' => '25.00',
            'quantity' => 1,
        ]);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once();
        $pdf
            ->shouldReceive('download')
            ->once()
            ->andReturn(response('pdf-binary', 200, [
                'Content-Type' => 'application/pdf',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with(
                'pdf.quote',
                Mockery::on(
                    fn(array $data) => (
                        $data['items'][0]['title'] === $auction->title
                        && $data['items'][0]['quantity'] === 1
                        && $data['winner']['username'] === $winner->username
                    ),
                ),
            )
            ->andReturn($pdf);

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/quotes/{$bid->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_stored_quote_download_requires_a_safe_existing_pdf_filename(): void
    {
        $admin = $this->createAdmin();
        $quotesPath = storage_path('app/quotes');
        $filename = 'stored-quote.pdf';

        if (!is_dir($quotesPath)) {
            mkdir($quotesPath, 0777, true);
        }

        file_put_contents("{$quotesPath}/{$filename}", 'pdf');

        try {
            $this->actingAs($admin)->get("/api/quotes/{$filename}")->assertOk()->assertDownload($filename);

            $this->actingAs($admin)->get('/api/quotes/not-a-pdf.txt')->assertNotFound();
        } finally {
            @unlink("{$quotesPath}/{$filename}");
        }
    }

    public function test_quote_download_rejects_mismatched_or_non_winning_bids(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction($this->createUser(), [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $otherAuction = $this->createAuction($this->createUser(), [
            'quantity' => 1,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $winningBid = $this->createBid($auction, $this->createUser(), [
            'amount' => '20.00',
            'quantity' => 1,
        ]);
        $losingBid = $this->createBid($auction, $this->createUser(), [
            'amount' => '10.00',
            'quantity' => 1,
        ]);
        $otherBid = $this->createBid($otherAuction, $this->createUser(), [
            'amount' => '50.00',
            'quantity' => 1,
        ]);

        $this->actingAs($admin)->get("/api/auctions/{$auction->id}/quotes/{$otherBid->id}")->assertNotFound();
        $this->actingAs($admin)->get("/api/auctions/{$auction->id}/quotes/{$losingBid->id}")->assertNotFound();
        $this->actingAs($admin)->get("/api/auctions/{$auction->id}/quotes/{$winningBid->id}")->assertOk();
    }

    public function test_admin_can_download_a_user_quote_scoped_to_a_round(): void
    {
        $admin = $this->createAdmin();
        $winner = $this->createUser();
        $round = $this->createRound(['name' => 'Spring Round', 'status' => 'ended']);
        $inRound = $this->createAuction(null, [
            'auction_round_id' => $round->id,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $outsideRound = $this->createAuction(null, [
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($inRound, $winner, ['amount' => '25.00', 'quantity' => 1]);
        $this->createBid($outsideRound, $winner, ['amount' => '40.00', 'quantity' => 1]);

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once();
        $pdf
            ->shouldReceive('download')
            ->once()
            ->with("quote_{$winner->username}_Spring_Round.pdf")
            ->andReturn(response('pdf-binary', 200, [
                'Content-Type' => 'application/pdf',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with(
                'pdf.quote',
                Mockery::on(
                    fn(array $data) => (
                        count($data['items']) === 1
                        && $data['items'][0]['title'] === $inRound->title
                        && $data['round_name'] === 'Spring Round'
                        && $data['winner']['username'] === $winner->username
                    ),
                ),
            )
            ->andReturn($pdf);

        $this
            ->actingAs($admin)
            ->get("/api/rounds/{$round->id}/users/{$winner->id}/quotes")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_leftover_purchase_quote_splits_vat_from_the_purchase_total(): void
    {
        $settings = \App\Models\SiteSetting::instance();
        $settings->invoice_btw_percentage = 21.0;
        $settings->save();

        $admin = $this->createAdmin();
        $buyer = $this->createUser();
        $auction = $this->createAuction(null, [
            'title' => 'Office Chair',
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $purchase = $this->createLeftoverPurchase($auction, $buyer, [
            'quantity' => 4,
            'price_per_item' => '30.25',
        ]);

        $captured = $this->capturePdfData("Office_Chair_{$buyer->username}.pdf");

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/leftover-purchases/{$purchase->id}/quotes")
            ->assertOk();

        $data = $captured();
        $this->assertSame($buyer->username, $data['winner']['username']);
        $this->assertSame(
            [
                [
                    'title' => 'Office Chair',
                    'quantity' => 4,
                    'price_per_item' => 30.25,
                    'total' => 121.0,
                ],
            ],
            $data['items'],
        );
        $this->assertSame(121.0, $data['total']);
        $this->assertSame(100.0, $data['subtotal']);
        $this->assertSame(21.0, $data['btw_amount']);
        $this->assertSame('21.00', $data['btw_percentage']);
        $this->assertMatchesRegularExpression('/^PAY-[A-Z0-9]{6}$/', $data['payment_reference']);
        $this->assertSame($data['payment_reference'], $buyer->fresh()->payment_reference);
    }

    public function test_leftover_purchase_quote_rejects_a_purchase_from_another_auction(): void
    {
        $admin = $this->createAdmin();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $otherAuction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $purchase = $this->createLeftoverPurchase($otherAuction);

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/leftover-purchases/{$purchase->id}/quotes")
            ->assertNotFound();
    }

    public function test_price_offer_quote_is_only_available_for_accepted_offers(): void
    {
        $settings = \App\Models\SiteSetting::instance();
        $settings->invoice_btw_percentage = 9.0;
        $settings->save();

        $admin = $this->createAdmin();
        $buyer = $this->createUser(['payment_reference' => 'PAY-FIXED1']);
        $auction = $this->createAuction(null, [
            'title' => 'Desk Lamp',
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $pending = $this->createLeftoverPriceOffer($auction, null, ['status' => 'pending']);
        $rejected = $this->createLeftoverPriceOffer($auction, null, ['status' => 'rejected']);
        $accepted = $this->createLeftoverPriceOffer($auction, $buyer, [
            'quantity' => 2,
            'offered_price_per_item' => '54.50',
            'status' => 'accepted',
        ]);

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/leftover-price-offers/{$pending->id}/quotes")
            ->assertNotFound();
        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/leftover-price-offers/{$rejected->id}/quotes")
            ->assertNotFound();

        $captured = $this->capturePdfData("Desk_Lamp_{$buyer->username}.pdf");

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/leftover-price-offers/{$accepted->id}/quotes")
            ->assertOk();

        $data = $captured();
        $this->assertSame(
            [
                [
                    'title' => 'Desk Lamp',
                    'quantity' => 2,
                    'price_per_item' => 54.5,
                    'total' => 109.0,
                ],
            ],
            $data['items'],
        );
        $this->assertSame(109.0, $data['total']);
        $this->assertSame(100.0, $data['subtotal']);
        $this->assertSame(9.0, $data['btw_amount']);
        $this->assertSame('9.00', $data['btw_percentage']);
        $this->assertSame('PAY-FIXED1', $data['payment_reference']);
    }

    public function test_winning_bid_quote_uses_the_uniform_clearing_price(): void
    {
        $admin = $this->createAdmin();
        $winner = $this->createUser();
        $auction = $this->createAuction(null, [
            'quantity' => 3,
            'max_per_bidder' => 3,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $bid = $this->createBid($auction, $winner, ['amount' => '40.00', 'quantity' => 2]);
        $this->createBid($auction, null, ['amount' => '30.25', 'quantity' => 1]);

        $captured = $this->capturePdfData();

        $this->actingAs($admin)->get("/api/auctions/{$auction->id}/quotes/{$bid->id}")->assertOk();

        $data = $captured();
        $this->assertSame(2, $data['items'][0]['quantity']);
        $this->assertSame(30.25, $data['items'][0]['price_per_item']);
        $this->assertSame(60.5, $data['total']);
        $this->assertSame(50.0, $data['subtotal']);
        $this->assertSame(10.5, $data['btw_amount']);
    }

    public function test_user_quote_combines_won_bids_purchases_and_accepted_offers(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $bidAuction = $this->createAuction(null, [
            'title' => 'Bid Item',
            'quantity' => 2,
            'max_per_bidder' => 2,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($bidAuction, $user, ['amount' => '20.00', 'quantity' => 2]);
        $lostAuction = $this->createAuction(null, [
            'title' => 'Lost Item',
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createBid($lostAuction, $user, ['amount' => '11.00']);
        $this->createBid($lostAuction, null, ['amount' => '50.00']);
        $leftoverAuction = $this->createAuction(null, [
            'title' => 'Leftover Item',
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPurchase($leftoverAuction, $user, ['quantity' => 2, 'price_per_item' => '7.50']);
        $this->createLeftoverPurchase($leftoverAuction, null, ['quantity' => 1, 'price_per_item' => '7.50']);
        $this->createLeftoverPriceOffer($leftoverAuction, $user, [
            'quantity' => 1,
            'offered_price_per_item' => '6.00',
            'status' => 'accepted',
        ]);
        $pendingAuction = $this->createAuction(null, [
            'title' => 'Pending Offer Item',
            'quantity' => 5,
            'status' => 'ended',
            'ends_at' => now()->subHour(),
        ]);
        $this->createLeftoverPriceOffer($pendingAuction, $user, ['status' => 'pending']);
        $activeAuction = $this->createAuction(null, [
            'title' => 'Active Item',
            'ends_at' => now()->addDay(),
        ]);
        $this->createBid($activeAuction, $user, ['amount' => '99.00']);

        $captured = $this->capturePdfData("quote_{$user->username}.pdf");

        $this->actingAs($admin)->get("/api/users/{$user->id}/quotes")->assertOk();

        $data = $captured();
        $items = collect($data['items'])->sortBy('total')->values()->all();
        $this->assertSame(
            [
                ['title' => 'Leftover Item', 'quantity' => 1, 'price_per_item' => 6.0, 'total' => 6.0],
                ['title' => 'Leftover Item', 'quantity' => 2, 'price_per_item' => 7.5, 'total' => 15.0],
                ['title' => 'Bid Item', 'quantity' => 2, 'price_per_item' => 20.0, 'total' => 40.0],
            ],
            $items,
        );
        $this->assertEqualsWithDelta(61.0, $data['total'], 0.001);
        $this->assertSame(50.41, $data['subtotal']);
        $this->assertSame(10.59, $data['btw_amount']);
        $this->assertNull($data['round_name']);
    }

    public function test_user_quote_returns_not_found_when_the_user_won_nothing(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $auction = $this->createAuction(null, ['status' => 'ended', 'ends_at' => now()->subHour()]);
        $this->createBid($auction, $user, ['amount' => '11.00']);
        $this->createBid($auction, null, ['amount' => '50.00']);

        $this->actingAs($admin)->get("/api/users/{$user->id}/quotes")->assertNotFound();
    }

    /**
     * @return \Closure(): array<string, mixed>
     */
    private function capturePdfData(?string $filename = null): \Closure
    {
        $captured = null;

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once();
        $download = $pdf->shouldReceive('download')->once();
        if ($filename !== null) {
            $download->with($filename);
        }
        $download->andReturn(response('pdf-binary', 200, [
            'Content-Type' => 'application/pdf',
        ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.quote', Mockery::on(function (array $data) use (&$captured) {
                $captured = $data;

                return true;
            }))
            ->andReturn($pdf);

        return function () use (&$captured): array {
            $this->assertIsArray($captured);

            return $captured;
        };
    }
}
