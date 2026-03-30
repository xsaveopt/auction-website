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
            $this
                ->actingAs($admin)
                ->get("/api/quotes/{$filename}")
                ->assertOk()
                ->assertDownload($filename);

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

        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/quotes/{$otherBid->id}")
            ->assertNotFound();
        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/quotes/{$losingBid->id}")
            ->assertNotFound();
        $this
            ->actingAs($admin)
            ->get("/api/auctions/{$auction->id}/quotes/{$winningBid->id}")
            ->assertOk();
    }
}
