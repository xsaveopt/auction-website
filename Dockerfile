FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

FROM dunglas/frankenphp:1-php8.5

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends curl unzip

RUN install-php-extensions pdo_sqlite bcmath opcache pcntl apcu igbinary redis mbstring gd

RUN <<EOF tee /usr/local/etc/php/conf.d/zz-app-perf.ini
; APCu
apc.enable_cli=1

; OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=32531
opcache.validate_timestamps=0
opcache.huge_code_pages=1
opcache.jit=1255
opcache.jit_buffer_size=128M

; Realpath cache — files don't change at runtime (validate_timestamps=0)
realpath_cache_size=4096K
realpath_cache_ttl=600

; General
memory_limit=256M
pcre.jit=1
EOF

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress

COPY --link . .
COPY --link --from=frontend /app/public/build public/build

RUN composer dump-autoload --optimize --no-interaction \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

RUN mkdir -p /data/caddy_data /data/caddy_config

COPY --chmod=755 docker-entrypoint.sh /docker-entrypoint.sh

EXPOSE 80 443 443/udp

VOLUME ["/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost:9113/up || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]
