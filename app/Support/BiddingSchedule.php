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

    public static function isBiddingOpen(?Carbon $at = null): bool
    {
        $now = $at ?? now();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        $current = $hour * 60 + $minute;

        [$startH, $startM] = array_map('intval', explode(':', self::closedStart()));
        [$endH, $endM] = array_map('intval', explode(':', self::closedEnd()));

        $start = $startH * 60 + $startM;
        $end = $endH * 60 + $endM;

        // Bidding is closed between start and end, open otherwise
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
            'is_open' => self::isBiddingOpen(),
            'server_time' => now()->toISOString(),
        ];
    }
}
