<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        if (!$app instanceof Application) {
            throw new \RuntimeException('bootstrap/app.php must return the application instance.');
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
