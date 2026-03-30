<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;

class BiddingSchedule
{
    public static function isEnabled(): bool
    {
        return SiteSetting::instance()->bidding_schedule_enabled;
    }

    public static function closedStart(): string
    {
        return SiteSetting::instance()->bidding_closed_start ?? '09:00';
    }

    public static function closedEnd(): string
    {
        return SiteSetting::instance()->bidding_closed_end ?? '18:00';
    }

    public static function weekendsOpen(): bool
    {
        return SiteSetting::instance()->bidding_weekends_open;
    }

    private static function isBiddingOpenFromSettings(SiteSetting $settings, ?Carbon $at = null): bool
    {
        if (!$settings->bidding_schedule_enabled) {
            return true;
        }

        $now = $at ?? now();

        if ($settings->bidding_weekends_open && $now->isWeekend()) {
            return true;
        }

        $current = ((int) $now->format('H') * 60) + (int) $now->format('i');

        [$startH, $startM] = array_map('intval', explode(':', $settings->bidding_closed_start ?? '09:00'));
        [$endH, $endM] = array_map('intval', explode(':', $settings->bidding_closed_end ?? '18:00'));

        $start = ($startH * 60) + $startM;
        $end = ($endH * 60) + $endM;

        return $current < $start || $current >= $end;
    }

    public static function isBiddingOpen(?Carbon $at = null): bool
    {
        return self::isBiddingOpenFromSettings(SiteSetting::instance(), $at);
    }

    public static function currencySymbol(): string
    {
        return SiteSetting::instance()->currency_symbol ?? '$';
    }

    /**
     * @return array{enabled: bool, window: int, extension: int}
     */
    public static function antiSniping(): array
    {
        $settings = SiteSetting::instance();

        return [
            'enabled' => $settings->anti_sniping_enabled,
            'window' => $settings->anti_sniping_window ?? 60,
            'extension' => $settings->anti_sniping_extension ?? 300,
        ];
    }

    /**
     * @return array{enabled: bool, closed_start: string, closed_end: string, weekends_open: bool, is_open: bool, server_time: string, server_time_local: string, currency_symbol: string, anti_sniping: array{enabled: bool, window: int, extension: int}, site_locked: bool, lock_message: string|null}
     */
    public static function toArray(): array
    {
        $settings = SiteSetting::instance();

        $closedStart = $settings->bidding_closed_start ?? '09:00';
        $closedEnd = $settings->bidding_closed_end ?? '18:00';

        return [
            'enabled' => $settings->bidding_schedule_enabled,
            'closed_start' => $closedStart,
            'closed_end' => $closedEnd,
            'weekends_open' => $settings->bidding_weekends_open,
            'is_open' => self::isBiddingOpenFromSettings($settings),
            'server_time' => now()->toISOString() ?? '',
            'server_time_local' => now()->format('H:i:s'),
            'currency_symbol' => $settings->currency_symbol ?? '$',
            'anti_sniping' => [
                'enabled' => $settings->anti_sniping_enabled,
                'window' => $settings->anti_sniping_window ?? 60,
                'extension' => $settings->anti_sniping_extension ?? 300,
            ],
            'site_locked' => $settings->is_locked,
            'lock_message' => $settings->lock_message,
        ];
    }
}
