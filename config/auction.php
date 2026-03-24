<?php

return [
    'bidding_schedule_enabled' => (bool) env('BIDDING_SCHEDULE_ENABLED', true),
    'bidding_closed_start' => env('BIDDING_CLOSED_START', '09:00'),
    'bidding_closed_end' => env('BIDDING_CLOSED_END', '18:00'),
    'weekends_open' => env('BIDDING_WEEKENDS_OPEN', true),
    'currency_symbol' => env('CURRENCY_SYMBOL', '$'),

    'anti_sniping_enabled' => (bool) env('ANTI_SNIPING_ENABLED', true),
    'anti_sniping_window' => (int) env('ANTI_SNIPING_WINDOW', 60),
    'anti_sniping_extension' => (int) env('ANTI_SNIPING_EXTENSION', 300),
    'mcp_api_key' => env('MCP_API_KEY'),

    'leftover_sales_enabled' => (bool) env('LEFTOVER_SALES_ENABLED', false),
    'leftover_price_factor' => (float) env('LEFTOVER_PRICE_FACTOR', 0.75),

    'company' => [
        'name' => env('COMPANY_NAME', ''),
        'street' => env('COMPANY_STREET', ''),
        'postal_code' => env('COMPANY_POSTAL_CODE', ''),
        'city' => env('COMPANY_CITY', ''),
        'kvk' => env('COMPANY_KVK', ''),
        'btw' => env('COMPANY_BTW', ''),
        'iban_1' => env('COMPANY_IBAN_1', ''),
        'iban_2' => env('COMPANY_IBAN_2', ''),
    ],

    'invoice' => [
        'btw_percentage' => (float) env('INVOICE_BTW_PERCENTAGE', 21),
        'payment_days' => (int) env('INVOICE_PAYMENT_DAYS', 30),
    ],
];
