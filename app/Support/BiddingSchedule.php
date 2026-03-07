<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class BiddingSchedule
{
    public static function closedStart(): string
    {
        return (string) config('auction.bidding_closed_start', '09:00');
    }

    public static function closedEnd(): string
    {
        return (string) config('auction.bidding_closed_end', '18:00');
    }

    public static function weekendsOpen(): bool
    {
        return (bool) config('auction.weekends_open', true);
    }

    public static function isBiddingOpen(?Carbon $at = null): bool
    {
        $now = $at ?? now();

        if (self::weekendsOpen() && $now->isWeekend()) {
            return true;
        }

        $current = (int) $now->format('H') * 60 + (int) $now->format('i');

        [$startH, $startM] = array_map('intval', explode(':', self::closedStart()));
        [$endH, $endM] = array_map('intval', explode(':', self::closedEnd()));

        $start = $startH * 60 + $startM;
        $end = $endH * 60 + $endM;

        // Bidding is closed between start and end on weekdays
        return $current < $start || $current >= $end;
    }

    /**
     * @return array{closed_start: string, closed_end: string, is_open: bool, server_time: string}
     */
    public static function toArray(): array
    {
        return [
            'closed_start' => self::closedStart(),
            'closed_end' => self::closedEnd(),
            'weekends_open' => self::weekendsOpen(),
            'is_open' => self::isBiddingOpen(),
            'server_time' => now()->toISOString(),
        ];
    }
}
