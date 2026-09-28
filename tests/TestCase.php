<?php

namespace Tests;

use App\Models\SiteSetting;
use App\Support\PrometheusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;
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
            'services.metrics.token' => 'test-metrics-token',
            'services.microsoft.client_id' => null,
            'services.microsoft.client_secret' => null,
            'services.webpush.public_key' => null,
            'services.webpush.private_key' => null,
            'services.webpush.subject' => null,
        ]);

        try {
            $settings = SiteSetting::instance();
            $settings->anti_sniping_enabled = false;
            $settings->bidding_schedule_enabled = false;
            $settings->leftover_sales_enabled = false;
            $settings->leftover_price_factor = 0.75;
            $settings->save();
        } catch (\Illuminate\Database\QueryException $e) {
        }
    }

    /**
     * @param string $command
     * @param array<string, mixed> $parameters
     */
    public function artisan($command, $parameters = []): PendingCommand
    {
        $pending = parent::artisan($command, $parameters);

        if (!$pending instanceof PendingCommand) {
            $this->fail('Console output mocking must stay enabled for artisan assertions.');
        }

        return $pending;
    }

    /**
     * @template TModel of Model
     * @param TModel $model
     * @param array<int, string>|string $with
     * @return TModel
     */
    protected function reload(Model $model, array|string $with = []): Model
    {
        $fresh = $model->fresh($with);

        if ($fresh === null) {
            $this->fail(sprintf('%s no longer exists.', $model::class));
        }

        return $fresh;
    }
}
