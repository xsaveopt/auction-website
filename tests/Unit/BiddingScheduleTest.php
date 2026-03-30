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
}
