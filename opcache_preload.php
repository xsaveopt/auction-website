<?php

/**
 * OPcache preload script.
 *
 * Compiles the Laravel framework and application PHP files into OPcache at
 * worker startup. Combined with opcache.validate_timestamps=0, these bytecodes
 * stay pinned in memory for the lifetime of the worker — eliminating per-request
 * compile overhead entirely.
 *
 * Configured via:
 *   opcache.preload=/app/opcache_preload.php
 *   opcache.preload_user=root
 */

$directories = [
    __DIR__ . '/vendor/laravel/framework/src',
    __DIR__ . '/vendor/illuminate',
    __DIR__ . '/app',
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            opcache_compile_file($file->getPathname());
        }
    }
}
