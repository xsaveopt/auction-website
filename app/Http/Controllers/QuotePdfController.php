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

        /** @var float $btwPercentage */
        $btwPercentage = config('auction.invoice.btw_percentage');
        $total = $totalOwed;
        $subtotal = round($total / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($total - $subtotal, 2);

        $data = [
            'quote_number' =>
                'Q-'
                    . str_pad((string) $auction->id, 4, '0', STR_PAD_LEFT)
                    . '-'
                    . str_pad((string) $bid->id, 4, '0', STR_PAD_LEFT),
            'auction' => [
                'id' => $auction->id,
                'title' => $auction->title,
                'description' => $auction->description,
                'ends_at' => $auction->ends_at->format('d-m-Y'),
                'quantity' => $auction->quantity,
                'bid_count' => $auction->bids->count(),
            ],
            'winner' => [
                'username' => $bid->user->username ?? 'Unknown',
                'bid_amount' => (float) $bid->amount,
                'bid_date' => $bid->created_at?->format('d-m-Y') ?? '',
                'won_quantity' => $wonQty,
                'total_owed' => $totalOwed,
            ],
            'clearing_price' => $clearingPrice,
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => config('auction.company'),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $total,
            'payment_days' => config('auction.invoice.payment_days'),
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
