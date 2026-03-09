<?php

return [
    'bidding_closed_start' => env('BIDDING_CLOSED_START', '09:00'),
    'bidding_closed_end' => env('BIDDING_CLOSED_END', '18:00'),
    'weekends_open' => env('BIDDING_WEEKENDS_OPEN', true),
    'currency_symbol' => env('CURRENCY_SYMBOL', '$'),
];
