<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\BiddingSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotePdfController extends Controller
{
    public function download(Auction $auction, Bid $bid): Response
    {
        abort_unless($bid->auction_id === $auction->id, 404);

        $bid->load('user:id,username');

        $result = $this->allocate($auction);
        $allocations = $result['allocations'];
        $clearingPrice = $result['clearing_price'];

        $wonQty = $allocations[$bid->id] ?? 0;
        abort_if($wonQty === 0, 404, 'This bid did not win any items.');

        $totalOwed = $wonQty * $clearingPrice;

        $data = [
            'auction' => [
                'title' => $auction->title,
                'description' => $auction->description,
                'ends_at' => $auction->ends_at->format('M j, Y \a\t H:i'),
            ],
            'winner' => [
                'username' => $bid->user->username ?? 'Unknown',
                'bid_amount' => (float) $bid->amount,
                'won_quantity' => $wonQty,
                'total_owed' => $totalOwed,
            ],
            'clearing_price' => $clearingPrice,
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('M j, Y \a\t H:i'),
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $filename = str_replace(' ', '_', $auction->title) . '_' . ($bid->user->username ?? 'user') . '.pdf';

        /** @var Response */
        return $pdf->download($filename);
    }

    /**
     * @return array{allocations: array<int, int>, clearing_price: float}
     */
    private function allocate(Auction $auction): array
    {
        $auction->load('bids');
        $sortedBids = $auction->bids->sortByDesc('amount')->values();
        $remaining = (int) $auction->quantity;
        /** @var array<int, int> $allocations */
        $allocations = [];
        $clearingPrice = (float) $auction->starting_price;

        foreach ($sortedBids as $b) {
            if ($remaining <= 0) {
                break;
            }

            $give = min((int) $b->quantity, $remaining);
            $allocations[$b->id] = $give;
            $remaining -= $give;
            $clearingPrice = (float) $b->amount;
        }

        return [
            'allocations' => $allocations,
            'clearing_price' => $clearingPrice,
        ];
    }
}
