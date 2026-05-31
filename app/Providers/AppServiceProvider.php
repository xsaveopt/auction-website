<?php

namespace App\Providers;

use App\Support\PrometheusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PrometheusService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);

        if (config('database.default') === 'sqlite') {
            $database = config()->string('database.connections.sqlite.database');
            if ($database !== ':memory:' && !is_file($database)) {
                return;
            }

            $pdo = DB::connection()->getPdo();
            $pdo->exec('PRAGMA cache_size = -64000');
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('PRAGMA mmap_size = 134217728');
            $pdo->exec('PRAGMA threads = 4');
            $pdo->exec('PRAGMA optimize');
        }
    }
}
