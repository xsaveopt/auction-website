<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class BiddingSchedule
{
    public static function isEnabled(): bool
    {
        return boolval(config('auction.bidding_schedule_enabled', true));
    }

    public static function closedStart(): string
    {
        /** @var string */
        return config('auction.bidding_closed_start', '09:00');
    }

    public static function closedEnd(): string
    {
        /** @var string */
        return config('auction.bidding_closed_end', '18:00');
    }

    public static function weekendsOpen(): bool
    {
        return boolval(config('auction.weekends_open', true));
    }

    public static function isBiddingOpen(?Carbon $at = null): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        $now = $at ?? now();

        if (self::weekendsOpen() && $now->isWeekend()) {
            return true;
        }

        $current = ((int) $now->format('H') * 60) + (int) $now->format('i');

        [$startH, $startM] = array_map('intval', explode(':', self::closedStart()));
        [$endH, $endM] = array_map('intval', explode(':', self::closedEnd()));

        $start = ($startH * 60) + $startM;
        $end = ($endH * 60) + $endM;

        // Bidding is closed between start and end on weekdays
        return $current < $start || $current >= $end;
    }

    public static function currencySymbol(): string
    {
        /** @var string */
        return config('auction.currency_symbol', '$');
    }

    /**
     * @return array{enabled: bool, window: int, extension: int}
     */
    public static function antiSniping(): array
    {
        /** @var int $window */
        $window = config('auction.anti_sniping_window', 60);
        /** @var int $extension */
        $extension = config('auction.anti_sniping_extension', 300);

        return [
            'enabled' => boolval(config('auction.anti_sniping_enabled', true)),
            'window' => $window,
            'extension' => $extension,
        ];
    }

    /**
     * @return array{enabled: bool, closed_start: string, closed_end: string, weekends_open: bool, is_open: bool, server_time: string, server_timezone: string, currency_symbol: string, anti_sniping: array{enabled: bool, window: int, extension: int}}
     */
    public static function toArray(): array
    {
        return [
            'enabled' => self::isEnabled(),
            'closed_start' => self::closedStart(),
            'closed_end' => self::closedEnd(),
            'weekends_open' => self::weekendsOpen(),
            'is_open' => self::isBiddingOpen(),
            'server_time' => now()->toISOString() ?? '',
            'server_timezone' => date_default_timezone_get(),
            'currency_symbol' => self::currencySymbol(),
            'anti_sniping' => self::antiSniping(),
        ];
    }
}
