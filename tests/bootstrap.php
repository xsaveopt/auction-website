<?php

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('tests_apcu_store')) {
    /**
     * @return array<string, mixed>
     */
    function &tests_apcu_store(): array
    {
        static $store = [];

        return $store;
    }
}

if (!function_exists('tests_reset_apcu_store')) {
    function tests_reset_apcu_store(): void
    {
        $store = &tests_apcu_store();
        $store = [];
    }
}

if (!function_exists('apcu_enabled')) {
    function apcu_enabled(): bool
    {
        return true;
    }
}

if (!function_exists('apcu_exists')) {
    function apcu_exists(string $key): bool
    {
        $store = &tests_apcu_store();

        return array_key_exists($key, $store);
    }
}

if (!function_exists('apcu_fetch')) {
    /**
     * @param  string|array<int, string>  $key
     * @return mixed
     */
    function apcu_fetch(string|array $key, ?bool &$success = null): mixed
    {
        $store = &tests_apcu_store();

        if (is_array($key)) {
            $values = [];
            foreach ($key as $item) {
                if (array_key_exists($item, $store)) {
                    $values[$item] = $store[$item];
                }
            }

            if (func_num_args() > 1) {
                $success = $values !== [];
            }

            return $values;
        }

        $found = array_key_exists($key, $store);

        if (func_num_args() > 1) {
            $success = $found;
        }

        return $found ? $store[$key] : false;
    }
}

if (!function_exists('apcu_store')) {
    function apcu_store(string $key, mixed $value, int $ttl = 0): bool
    {
        $store = &tests_apcu_store();
        $store[$key] = $value;

        return true;
    }
}

if (!function_exists('apcu_add')) {
    function apcu_add(string $key, mixed $value, int $ttl = 0): bool
    {
        $store = &tests_apcu_store();

        if (array_key_exists($key, $store)) {
            return false;
        }

        $store[$key] = $value;

        return true;
    }
}

if (!function_exists('apcu_delete')) {
    /**
     * @param  string|array<int, string>  $key
     */
    function apcu_delete(string|array $key): bool
    {
        $store = &tests_apcu_store();

        foreach ((array) $key as $item) {
            unset($store[$item]);
        }

        return true;
    }
}

if (!function_exists('apcu_inc')) {
    function apcu_inc(string $key, int $step = 1, ?bool &$success = null): int|false
    {
        $store = &tests_apcu_store();
        $current = (int) ($store[$key] ?? 0);
        $store[$key] = $current + $step;

        if (func_num_args() > 2) {
            $success = true;
        }

        return $store[$key];
    }
}

if (!function_exists('apcu_dec')) {
    function apcu_dec(string $key, int $step = 1, ?bool &$success = null): int|false
    {
        $store = &tests_apcu_store();
        $current = (int) ($store[$key] ?? 0);
        $store[$key] = $current - $step;

        if (func_num_args() > 2) {
            $success = true;
        }

        return $store[$key];
    }
}

if (!function_exists('apcu_cas')) {
    function apcu_cas(string $key, int $old, int $new): bool
    {
        $store = &tests_apcu_store();
        $current = (int) ($store[$key] ?? 0);

        if ($current !== $old) {
            return false;
        }

        $store[$key] = $new;

        return true;
    }
}

if (!function_exists('apcu_key_info')) {
    /**
     * @return array{mtime: int}|false
     */
    function apcu_key_info(string $key): array|false
    {
        if (!apcu_exists($key)) {
            return false;
        }

        return ['mtime' => time()];
    }
}
