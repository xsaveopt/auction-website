<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'is_locked',
        'lock_message',
        'bidding_schedule_enabled',
        'bidding_closed_start',
        'bidding_closed_end',
        'bidding_weekends_open',
        'currency_symbol',
        'anti_sniping_enabled',
        'anti_sniping_window',
        'anti_sniping_extension',
        'leftover_sales_enabled',
        'leftover_price_factor',
        'company_name',
        'company_street',
        'company_postal_code',
        'company_city',
        'company_kvk',
        'company_btw',
        'company_iban_1',
        'company_iban_2',
        'invoice_btw_percentage',
        'invoice_payment_days',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'bidding_schedule_enabled' => 'boolean',
        'bidding_weekends_open' => 'boolean',
        'anti_sniping_enabled' => 'boolean',
        'anti_sniping_window' => 'integer',
        'anti_sniping_extension' => 'integer',
        'leftover_sales_enabled' => 'boolean',
        'leftover_price_factor' => 'float',
        'invoice_btw_percentage' => 'float',
        'invoice_payment_days' => 'integer',
    ];

    public static function instance(): self
    {
        /** @var self */
        return self::firstOrCreate(['id' => 1]);
    }

    public static function isLocked(): bool
    {
        return self::instance()->is_locked;
    }

    /**
     * @return array{name: string, street: string, postal_code: string, city: string, kvk: string, btw: string, iban_1: string, iban_2: string}
     */
    public function company(): array
    {
        return [
            'name' => $this->company_name ?? '',
            'street' => $this->company_street ?? '',
            'postal_code' => $this->company_postal_code ?? '',
            'city' => $this->company_city ?? '',
            'kvk' => $this->company_kvk ?? '',
            'btw' => $this->company_btw ?? '',
            'iban_1' => $this->company_iban_1 ?? '',
            'iban_2' => $this->company_iban_2 ?? '',
        ];
    }
}
