<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\LeftoverPurchase;
use App\Models\OverrideSale;
use App\Models\User;
use App\Support\AuctionService;
use App\Support\PrometheusService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OverrideSaleController extends Controller
{
    public function __construct(
        protected AuctionService $auctionService,
    ) {}

    public function index(): JsonResponse
    {
        $sales = OverrideSale::query()
            ->with(['user:id,username', 'purchases.auction:id,title'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'override_sales' => $sales->map(fn(OverrideSale $sale) => $this->saleResponse($sale))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{username: string, items: list<array{auction_id: int, quantity: int, price_per_item: numeric-string|int|float}>} $validated */
        $validated = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.auction_id' => ['required', 'integer', 'distinct', 'exists:auctions,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_per_item' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ]);

        /** @var User $buyer */
        $buyer = User::query()->where('username', $validated['username'])->firstOrFail();
        /** @var User $admin */
        $admin = $request->user();

        $result = DB::transaction(function () use ($validated, $buyer, $admin): OverrideSale {
            $sale = OverrideSale::query()->create(['user_id' => $buyer->id, 'created_by' => $admin->id]);

            foreach ($validated['items'] as $item) {
                /** @var Auction $auction */
                $auction = Auction::query()->findOrFail($item['auction_id']);
                $this->auctionService->lockForUpdate($auction);

                if ($auction->isActive()) {
                    throw new HttpResponseException(response()->json([
                        'message' => "{$auction->title} is still active.",
                    ], 422));
                }

                if ($auction->status === 'cancelled') {
                    throw new HttpResponseException(response()->json([
                        'message' => "{$auction->title} was cancelled.",
                    ], 422));
                }

                $auction->load(['bids', 'leftoverPurchases']);
                $available = $this->auctionService->availableLeftoverQuantity($auction);

                if ($item['quantity'] > $available) {
                    throw new HttpResponseException(response()->json([
                        'message' => "Only {$available} item(s) of {$auction->title} available.",
                    ], 422));
                }

                $auction
                    ->leftoverPurchases()
                    ->create([
                        'user_id' => $buyer->id,
                        'override_sale_id' => $sale->id,
                        'quantity' => $item['quantity'],
                        'price_per_item' => round((float) $item['price_per_item'], 2),
                        'is_override' => true,
                    ]);

                $this->auctionService->closePendingOffersIfSoldOut($auction);
            }

            return $sale;
        });

        $result->load(['user:id,username', 'purchases.auction:id,title']);
        $response = $this->saleResponse($result);

        app(PrometheusService::class)->recordEvent(
            'leftover_items_sold',
            ['override'],
            (int) $result->purchases->sum(fn(LeftoverPurchase $p) => $p->quantity),
        );

        AuditLog::record($admin, 'override_sale.create', $result, [
            'buyer' => $buyer->username,
            'items' => $response['items'],
            'total' => $response['total'],
        ]);

        return response()->json(['override_sale' => $response], 201);
    }

    public function destroy(Request $request, OverrideSale $overrideSale): JsonResponse
    {
        $overrideSale->load(['user:id,username', 'purchases.auction:id,title']);
        $response = $this->saleResponse($overrideSale);

        DB::transaction(function () use ($overrideSale): void {
            $overrideSale
                ->purchases()
                ->get()
                ->each(fn(LeftoverPurchase $purchase) => $purchase->delete());
            $overrideSale->delete();
        });

        /** @var User $admin */
        $admin = $request->user();
        AuditLog::record($admin, 'override_sale.delete', $overrideSale, [
            'buyer' => $response['user']['username'],
            'items' => $response['items'],
            'total' => $response['total'],
        ]);

        return response()->json(['message' => 'Override sale deleted.']);
    }

    /**
     * @return array{id: int, user: array{id: int|null, username: string|null}, items: list<array{auction_id: int, auction_title: string|null, quantity: int, price_per_item: string, total: string}>, total: string, created_at: string|null}
     */
    private function saleResponse(OverrideSale $sale): array
    {
        $total = 0.0;
        $items = [];

        foreach ($sale->purchases as $purchase) {
            $lineTotal = round($purchase->quantity * (float) $purchase->price_per_item, 2);
            $total += $lineTotal;
            $items[] = [
                'auction_id' => $purchase->auction_id,
                'auction_title' => $purchase->auction?->title,
                'quantity' => $purchase->quantity,
                'price_per_item' => number_format((float) $purchase->price_per_item, 2, '.', ''),
                'total' => number_format($lineTotal, 2, '.', ''),
            ];
        }

        return [
            'id' => $sale->id,
            'user' => [
                'id' => $sale->user?->id,
                'username' => $sale->user?->username,
            ],
            'items' => $items,
            'total' => number_format($total, 2, '.', ''),
            'created_at' => $sale->created_at?->format('Y-m-d\TH:i:sP'),
        ];
    }
}
