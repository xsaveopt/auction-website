<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_schedule_is_public_and_reports_open_bidding_when_disabled(): void
    {
        $this
            ->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('schedule.enabled', false)
            ->assertJsonPath('schedule.is_open', true)
            ->assertJsonPath('schedule.currency_symbol', '$')
            ->assertJsonPath('schedule.anti_sniping.enabled', false)
            ->assertJsonPath('schedule.site_locked', false)
            ->assertJsonStructure([
                'schedule' => [
                    'enabled',
                    'closed_start',
                    'closed_end',
                    'weekends_open',
                    'is_open',
                    'server_time',
                    'server_time_local',
                    'currency_symbol',
                    'anti_sniping' => ['enabled', 'window', 'extension'],
                    'site_locked',
                    'lock_message',
                ],
            ]);
    }

    public function test_schedule_reflects_configured_settings_and_closed_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-23 10:00:00'));

        $settings = SiteSetting::instance();
        $settings->bidding_schedule_enabled = true;
        $settings->bidding_closed_start = '09:00';
        $settings->bidding_closed_end = '18:00';
        $settings->bidding_weekends_open = false;
        $settings->currency_symbol = '€';
        $settings->anti_sniping_enabled = true;
        $settings->anti_sniping_window = 90;
        $settings->anti_sniping_extension = 120;
        $settings->is_locked = true;
        $settings->lock_message = 'Maintenance';
        $settings->save();

        $this
            ->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('schedule.enabled', true)
            ->assertJsonPath('schedule.closed_start', '09:00')
            ->assertJsonPath('schedule.closed_end', '18:00')
            ->assertJsonPath('schedule.weekends_open', false)
            ->assertJsonPath('schedule.is_open', false)
            ->assertJsonPath('schedule.server_time_local', '10:00:00')
            ->assertJsonPath('schedule.currency_symbol', '€')
            ->assertJsonPath('schedule.anti_sniping.enabled', true)
            ->assertJsonPath('schedule.anti_sniping.window', 90)
            ->assertJsonPath('schedule.anti_sniping.extension', 120)
            ->assertJsonPath('schedule.site_locked', true)
            ->assertJsonPath('schedule.lock_message', 'Maintenance');
    }
}
