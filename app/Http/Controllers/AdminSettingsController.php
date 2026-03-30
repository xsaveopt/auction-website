<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $s = SiteSetting::instance();

        return response()->json([
            'settings' => [
                'bidding_schedule_enabled' => $s->bidding_schedule_enabled,
                'bidding_closed_start' => $s->bidding_closed_start ?? '09:00',
                'bidding_closed_end' => $s->bidding_closed_end ?? '18:00',
                'bidding_weekends_open' => $s->bidding_weekends_open,
                'currency_symbol' => $s->currency_symbol ?? '$',
                'anti_sniping_enabled' => $s->anti_sniping_enabled,
                'anti_sniping_window' => $s->anti_sniping_window ?? 60,
                'anti_sniping_extension' => $s->anti_sniping_extension ?? 300,
                'leftover_sales_enabled' => $s->leftover_sales_enabled,
                'leftover_price_factor' => $s->leftover_price_factor ?? 0.75,
                'company_name' => $s->company_name ?? '',
                'company_street' => $s->company_street ?? '',
                'company_postal_code' => $s->company_postal_code ?? '',
                'company_city' => $s->company_city ?? '',
                'company_kvk' => $s->company_kvk ?? '',
                'company_btw' => $s->company_btw ?? '',
                'company_iban_1' => $s->company_iban_1 ?? '',
                'company_iban_2' => $s->company_iban_2 ?? '',
                'invoice_btw_percentage' => $s->invoice_btw_percentage ?? 21.0,
                'invoice_payment_days' => $s->invoice_payment_days ?? 30,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'bidding_schedule_enabled' => ['sometimes', 'boolean'],
            'bidding_closed_start' => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'bidding_closed_end' => ['sometimes', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'bidding_weekends_open' => ['sometimes', 'boolean'],
            'currency_symbol' => ['sometimes', 'string', 'max:10'],
            'anti_sniping_enabled' => ['sometimes', 'boolean'],
            'anti_sniping_window' => ['sometimes', 'integer', 'min:0'],
            'anti_sniping_extension' => ['sometimes', 'integer', 'min:0'],
            'leftover_sales_enabled' => ['sometimes', 'boolean'],
            'leftover_price_factor' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_street' => ['sometimes', 'string', 'max:255'],
            'company_postal_code' => ['sometimes', 'string', 'max:20'],
            'company_city' => ['sometimes', 'string', 'max:255'],
            'company_kvk' => ['sometimes', 'string', 'max:50'],
            'company_btw' => ['sometimes', 'string', 'max:50'],
            'company_iban_1' => ['sometimes', 'string', 'max:50'],
            'company_iban_2' => ['sometimes', 'string', 'max:50'],
            'invoice_btw_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'invoice_payment_days' => ['sometimes', 'integer', 'min:1'],
        ]);

        $settings = SiteSetting::instance();
        $settings->fill($validated);
        $settings->updated_at = now();
        $settings->save();

        return $this->show();
    }
}
