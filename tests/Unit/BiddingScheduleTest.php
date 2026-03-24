<?php

namespace Tests\Unit;

use App\Support\BiddingSchedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiddingScheduleTest extends TestCase
{
    public function test_bidding_is_always_open_when_schedule_is_disabled(): void
    {
        config(['auction.bidding_schedule_enabled' => false]);

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 10:00:00')));
    }

    public function test_bidding_is_closed_during_the_weekday_window(): void
    {
        config([
            'auction.bidding_schedule_enabled' => true,
            'auction.bidding_closed_start' => '09:00',
            'auction.bidding_closed_end' => '18:00',
            'auction.weekends_open' => false,
        ]);

        $this->assertFalse(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-23 10:00:00')));
    }

    public function test_weekends_can_stay_open_even_inside_the_closed_window(): void
    {
        config([
            'auction.bidding_schedule_enabled' => true,
            'auction.bidding_closed_start' => '09:00',
            'auction.bidding_closed_end' => '18:00',
            'auction.weekends_open' => true,
        ]);

        $this->assertTrue(BiddingSchedule::isBiddingOpen(Carbon::parse('2026-03-28 10:00:00')));
    }
}
