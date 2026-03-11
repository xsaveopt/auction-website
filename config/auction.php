<?php

return [
    'bidding_closed_start' => env('BIDDING_CLOSED_START', '09:00'),
    'bidding_closed_end' => env('BIDDING_CLOSED_END', '18:00'),
    'weekends_open' => env('BIDDING_WEEKENDS_OPEN', true),
    'currency_symbol' => env('CURRENCY_SYMBOL', '$'),
    'mcp_api_key' => env('MCP_API_KEY'),

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
