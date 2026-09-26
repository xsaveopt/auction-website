<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Support\BiddingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiddingScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bidding_is_always_open_when_schedule_is_disabled(): void
    {
        $settings = SiteSetting::instance();
        $settings->bidding_schedule_enabled = false;
        $settings->save();

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 10:00:00')));
    }

    public function test_bidding_is_closed_during_the_weekday_window(): void
    {
        $settings = SiteSetting::instance();
        $settings->bidding_schedule_enabled = true;
        $settings->bidding_closed_start = '09:00';
        $settings->bidding_closed_end = '18:00';
        $settings->bidding_weekends_open = false;
        $settings->save();

        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 10:00:00')));
    }

    public function test_weekends_can_stay_open_even_inside_the_closed_window(): void
    {
        $settings = SiteSetting::instance();
        $settings->bidding_schedule_enabled = true;
        $settings->bidding_closed_start = '09:00';
        $settings->bidding_closed_end = '18:00';
        $settings->bidding_weekends_open = true;
        $settings->save();

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-28 10:00:00')));
    }

    public function test_closed_window_start_is_inclusive_and_end_is_exclusive(): void
    {
        $this->configureClosedWindow('09:00', '18:00');

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 08:59:00')));
        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 09:00:00')));
        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 17:59:00')));
        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 18:00:00')));
    }

    public function test_closed_window_crossing_midnight_is_closed_late_in_the_evening(): void
    {
        $this->configureClosedWindow('22:00', '06:00');

        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 23:00:00')));
    }

    public function test_closed_window_crossing_midnight_is_closed_early_in_the_morning(): void
    {
        $this->configureClosedWindow('22:00', '06:00');

        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-24 03:00:00')));
    }

    public function test_closed_window_crossing_midnight_is_open_during_the_day(): void
    {
        $this->configureClosedWindow('22:00', '06:00');

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 12:00:00')));
        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-24 06:00:00')));
    }

    private function configureClosedWindow(string $start, string $end): void
    {
        $settings = SiteSetting::instance();
        $settings->bidding_schedule_enabled = true;
        $settings->bidding_closed_start = $start;
        $settings->bidding_closed_end = $end;
        $settings->bidding_weekends_open = false;
        $settings->save();
    }
}
