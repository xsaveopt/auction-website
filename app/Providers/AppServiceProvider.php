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
            $database = (string) config('database.connections.sqlite.database');
            // Skip if the file hasn't been created yet (CI before first migration,
            // fresh installs). PRAGMAs are performance-only; missing them on a
            // non-existent DB is harmless — they'll apply on the next boot.
            if ($database !== ':memory:' && !is_file($database)) {
                return;
            }

            $pdo = DB::connection()->getPdo();
            $pdo->exec('PRAGMA cache_size = -64000');
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('PRAGMA mmap_size = 134217728');
            // Allow SQLite to spawn worker threads for parallel query steps
            // (sorting, multi-table joins). Each Octane worker has its own
            // connection, so this parallelism is within a single request query.
            $pdo->exec('PRAGMA threads = 4');
            // Update query-planner statistics for tables that have changed
            // significantly since the last ANALYZE. Cheap at startup; prevents
            // stale stats from causing bad query plans as data grows.
            $pdo->exec('PRAGMA optimize');
        }
    }
}
