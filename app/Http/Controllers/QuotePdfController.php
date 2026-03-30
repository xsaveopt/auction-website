<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\LeftoverPriceOffer;
use App\Models\LeftoverPurchase;
use App\Models\User;
use App\Support\AuctionService;
use App\Support\BiddingSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuotePdfController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function download(Auction $auction, Bid $bid): Response
    {
        abort_unless($bid->auction_id === $auction->id, 404);

        $bid->load('user:id,username,payment_reference');
        $auction->load('bids');

        $result = $this->auctionService->allocate($auction);
        $allocations = $result['allocations'];
        $prices = $result['prices'];

        $wonQty = $allocations[$bid->id] ?? 0;
        abort_if($wonQty === 0, 404, 'This bid did not win any items.');

        $pricePerItem = $prices[$bid->id] ?? (float) $bid->amount;
        $totalOwed = round($wonQty * $pricePerItem, 2);

        /** @var float $btwPercentage */
        $btwPercentage = config('auction.invoice.btw_percentage');
        $subtotal = round($totalOwed / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($totalOwed - $subtotal, 2);

        $data = [
            'winner' => [
                'username' => $bid->user->username ?? 'Unknown',
            ],
            'items' => [
                [
                    'title' => $auction->title,
                    'quantity' => $wonQty,
                    'price_per_item' => $pricePerItem,
                    'total' => $totalOwed,
                ],
            ],
            'payment_reference' => $bid->user ? $this->getOrCreatePaymentReference($bid->user) : null,
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => config('auction.company'),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $totalOwed,
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $filename = str_replace(' ', '_', $auction->title) . '_' . ($bid->user->username ?? 'user') . '.pdf';

        /** @var Response */
        return $pdf->download($filename);
    }

    public function downloadForLeftoverPurchase(Auction $auction, LeftoverPurchase $leftoverPurchase): Response
    {
        abort_unless($leftoverPurchase->auction_id === $auction->id, 404);

        $leftoverPurchase->load('user:id,username,payment_reference');

        $pricePerItem = (float) $leftoverPurchase->price_per_item;
        $totalOwed = round($leftoverPurchase->quantity * $pricePerItem, 2);

        /** @var float $btwPercentage */
        $btwPercentage = config('auction.invoice.btw_percentage');
        $subtotal = round($totalOwed / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($totalOwed - $subtotal, 2);

        $data = [
            'winner' => [
                'username' => $leftoverPurchase->user->username ?? 'Unknown',
            ],
            'items' => [
                [
                    'title' => $auction->title,
                    'quantity' => $leftoverPurchase->quantity,
                    'price_per_item' => $pricePerItem,
                    'total' => $totalOwed,
                ],
            ],
            'payment_reference' => $leftoverPurchase->user
                ? $this->getOrCreatePaymentReference($leftoverPurchase->user)
                : null,
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => config('auction.company'),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $totalOwed,
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $filename =
            str_replace(' ', '_', $auction->title) . '_' . ($leftoverPurchase->user->username ?? 'user') . '.pdf';

        /** @var Response */
        return $pdf->download($filename);
    }

    public function downloadForLeftoverPriceOffer(Auction $auction, LeftoverPriceOffer $leftoverPriceOffer): Response
    {
        abort_unless($leftoverPriceOffer->auction_id === $auction->id, 404);
        abort_unless($leftoverPriceOffer->status === 'accepted', 404);

        $leftoverPriceOffer->load('user:id,username,payment_reference');

        $pricePerItem = (float) $leftoverPriceOffer->offered_price_per_item;
        $totalOwed = round($leftoverPriceOffer->quantity * $pricePerItem, 2);

        /** @var float $btwPercentage */
        $btwPercentage = config('auction.invoice.btw_percentage');
        $subtotal = round($totalOwed / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($totalOwed - $subtotal, 2);

        $data = [
            'winner' => [
                'username' => $leftoverPriceOffer->user->username ?? 'Unknown',
            ],
            'items' => [
                [
                    'title' => $auction->title,
                    'quantity' => $leftoverPriceOffer->quantity,
                    'price_per_item' => $pricePerItem,
                    'total' => $totalOwed,
                ],
            ],
            'payment_reference' => $leftoverPriceOffer->user
                ? $this->getOrCreatePaymentReference($leftoverPriceOffer->user)
                : null,
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => config('auction.company'),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $totalOwed,
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $filename =
            str_replace(' ', '_', $auction->title) . '_' . ($leftoverPriceOffer->user->username ?? 'user') . '.pdf';

        /** @var Response */
        return $pdf->download($filename);
    }

    public function downloadForUser(User $user): Response
    {
        $userBidAuctionIds = Bid::where('user_id', $user->id)->pluck('auction_id');
        $userPurchaseAuctionIds = LeftoverPurchase::where('user_id', $user->id)->pluck('auction_id');
        $userOfferAuctionIds = LeftoverPriceOffer::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('auction_id');
        $auctionIds = $userBidAuctionIds
            ->merge($userPurchaseAuctionIds)
            ->merge($userOfferAuctionIds)
            ->unique()
            ->values();

        $auctions = Auction::query()
            ->whereIn('id', $auctionIds)
            ->with([
                'bids',
                'leftoverPurchases' => fn(\Illuminate\Database\Eloquent\Relations\Relation $q) => $q->where(
                    'user_id',
                    $user->id,
                ),
                'leftoverPriceOffers' => fn(\Illuminate\Database\Eloquent\Relations\Relation $q) => $q->where(
                    'user_id',
                    $user->id,
                )->where('status', 'accepted'),
            ])
            ->get()
            ->filter(fn($a) => !$a->isActive());

        $items = [];
        $totalOwed = 0.0;

        foreach ($auctions as $auction) {
            $result = $this->auctionService->allocate($auction);
            $allocations = $result['allocations'];
            $prices = $result['prices'];

            foreach ($auction->bids as $bid) {
                if ($bid->user_id !== $user->id) {
                    continue;
                }
                $wonQty = $allocations[$bid->id] ?? 0;
                if ($wonQty <= 0) {
                    continue;
                }
                $pricePerItem = $prices[$bid->id] ?? (float) $bid->amount;
                $itemTotal = round($wonQty * $pricePerItem, 2);
                $items[] = [
                    'title' => $auction->title,
                    'quantity' => $wonQty,
                    'price_per_item' => $pricePerItem,
                    'total' => $itemTotal,
                ];
                $totalOwed += $itemTotal;
            }

            foreach ($auction->leftoverPurchases as $purchase) {
                $pricePerItem = (float) $purchase->price_per_item;
                $itemTotal = round($purchase->quantity * $pricePerItem, 2);
                $items[] = [
                    'title' => $auction->title,
                    'quantity' => $purchase->quantity,
                    'price_per_item' => $pricePerItem,
                    'total' => $itemTotal,
                ];
                $totalOwed += $itemTotal;
            }

            foreach ($auction->leftoverPriceOffers as $offer) {
                $pricePerItem = (float) $offer->offered_price_per_item;
                $itemTotal = round($offer->quantity * $pricePerItem, 2);
                $items[] = [
                    'title' => $auction->title,
                    'quantity' => $offer->quantity,
                    'price_per_item' => $pricePerItem,
                    'total' => $itemTotal,
                ];
                $totalOwed += $itemTotal;
            }
        }

        abort_if(empty($items), 404, 'No won items found for this user.');

        /** @var float $btwPercentage */
        $btwPercentage = config('auction.invoice.btw_percentage');
        $subtotal = round($totalOwed / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($totalOwed - $subtotal, 2);

        $data = [
            'winner' => [
                'username' => $user->username,
            ],
            'items' => $items,
            'payment_reference' => $this->getOrCreatePaymentReference($user),
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => config('auction.company'),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $totalOwed,
        ];

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $filename = 'quote_' . $user->username . '.pdf';

        /** @var Response */
        return $pdf->download($filename);
    }

    public function downloadStored(string $filename): BinaryFileResponse
    {
        $path = storage_path("app/quotes/{$filename}");

        abort_unless(str_ends_with($filename, '.pdf') && !str_contains($filename, '/') && file_exists($path), 404);

        return response()->download($path);
    }

    private function getOrCreatePaymentReference(User $user): string
    {
        if ($user->payment_reference) {
            return $user->payment_reference;
        }

        do {
            $ref = 'PAY-' . strtoupper(Str::random(6));
        } while (User::where('payment_reference', $ref)->exists());

        $user->payment_reference = $ref;
        $user->save();

        return $ref;
    }
}
