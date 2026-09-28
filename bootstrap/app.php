<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\RecordPrometheusMetrics::class);
        $middleware->append(\App\Http\Middleware\LogRequest::class);
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\ApiKeyMiddleware::class,
            \App\Http\Middleware\VerifyCsrfUnlessApiKey::class,
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'sso' => \App\Http\Middleware\EnsureSsoAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
