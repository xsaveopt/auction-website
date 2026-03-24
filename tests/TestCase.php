<?php

namespace Tests;

use App\Support\PrometheusService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithAuctionData;
use Tests\Fakes\FakePrometheusService;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithAuctionData;

    protected function setUp(): void
    {
        parent::setUp();

        tests_reset_apcu_store();

        $this->app->instance(PrometheusService::class, new FakePrometheusService());

        config([
            'auction.anti_sniping_enabled' => false,
            'auction.bidding_schedule_enabled' => false,
            'auction.leftover_price_factor' => 0.75,
            'auction.leftover_sales_enabled' => false,
            'services.metrics.token' => 'test-metrics-token',
            'services.microsoft.client_id' => null,
            'services.microsoft.client_secret' => null,
            'services.webpush.public_key' => null,
            'services.webpush.private_key' => null,
            'services.webpush.subject' => null,
        ]);
    }
}
