<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Support\BiddingSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateQuoteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-quote
                            {--title= : Item title / description}
                            {--buyer= : Buyer name}
                            {--price= : Total price per item (incl. BTW)}
                            {--quantity=1 : Number of items}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a standalone quote PDF (no auction required)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var string|null $title */
        $title = $this->option('title') ?? $this->ask('Item title');
        /** @var string|null $buyer */
        $buyer = $this->option('buyer') ?? $this->ask('Buyer name');
        /** @var string|null $price */
        $price = $this->option('price') ?? $this->ask('Price per item (incl. BTW)');
        $quantity = (int) $this->option('quantity');

        if (!$title || !$buyer || !$price) {
            $this->error('Title, buyer, and price are required.');
            return;
        }

        $siteSettings = SiteSetting::instance();
        $pricePerItem = (float) $price;
        $totalOwed = $quantity * $pricePerItem;

        $btwPercentage = $siteSettings->invoice_btw_percentage ?? 21.0;
        $total = $totalOwed;
        $subtotal = round($total / (1 + ($btwPercentage / 100)), 2);
        $btwAmount = round($total - $subtotal, 2);

        $data = [
            'winner' => [
                'username' => $buyer,
            ],
            'payment_reference' => 'PAY-' . strtoupper(Str::random(6)),
            'items' => [
                [
                    'title' => $title,
                    'quantity' => $quantity,
                    'price_per_item' => $pricePerItem,
                    'total' => $totalOwed,
                ],
            ],
            'currency' => BiddingSchedule::currencySymbol(),
            'generated_at' => now()->format('d-m-Y'),
            'company' => $siteSettings->company(),
            'subtotal' => $subtotal,
            'btw_percentage' => number_format($btwPercentage, 2),
            'btw_amount' => $btwAmount,
            'total' => $total,
        ];

        $this->table(['', ''], [
            ['Item', $title],
            ['Buyer', $buyer],
            ['Price/item', BiddingSchedule::currencySymbol() . ' ' . number_format($pricePerItem, 2, ',', '.')],
            ['Quantity', $quantity],
            ['Subtotal (excl. BTW)', BiddingSchedule::currencySymbol() . ' ' . number_format($subtotal, 2, ',', '.')],
            [
                'BTW (' . number_format($btwPercentage, 0) . '%)',
                BiddingSchedule::currencySymbol() . ' ' . number_format($btwAmount, 2, ',', '.'),
            ],
            ['Total', BiddingSchedule::currencySymbol() . ' ' . number_format($total, 2, ',', '.')],
        ]);

        if (!$this->confirm('Generate this quote?')) {
            return;
        }

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quote', $data);
        $pdf->setPaper('a4');

        $slug = Str::slug($title);
        $buyerSlug = Str::slug($buyer);
        $filename = "{$slug}_{$buyerSlug}_" . now()->format('Ymd_His') . '.pdf';

        $dir = storage_path('app/quotes');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->save("{$dir}/{$filename}");

        /** @var string $appUrl */
        $appUrl = config('app.url');
        $url = rtrim($appUrl, '/') . "/api/quotes/{$filename}";

        $this->newLine();
        $this->info('Quote generated successfully.');
        $this->line("File: {$dir}/{$filename}");
        $this->line("Download: {$url}");
    }
}
