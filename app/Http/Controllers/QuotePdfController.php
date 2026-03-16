<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotePdfController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function download(Auction $auction, Bid $bid): Response
    {
        abort_unless($bid->auction_id === $auction->id, 404);

        $bid->load('user:id,username');
        $auction->load('bids');

        $result = $this->auctionService->allocate($auction);
        $allocations = $result['allocations'];
        $prices = $result['prices'];

        $wonQty = $allocations[$bid->id] ?? 0;
        abort_if($wonQty === 0, 404, 'This bid did not win any items.');

        $pricePerItem = $prices[$bid->id] ?? (float) $bid->amount;
        $totalOwed = $wonQty * $pricePerItem;

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
            'clearing_price' => $pricePerItem,
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
}
