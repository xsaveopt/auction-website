FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

FROM dunglas/frankenphp:1-php8

RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_sqlite bcmath opcache pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

COPY . .
COPY --from=frontend /app/public/build public/build

RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN mkdir -p /data /data/caddy_data /data/caddy_config

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/data/database.sqlite
ENV OCTANE_SERVER=frankenphp
ENV SERVER_NAME=":80"
ENV BIDDING_CLOSED_START="09:00"
ENV BIDDING_CLOSED_END="18:00"

EXPOSE 80 443

VOLUME ["/data"]

ENTRYPOINT ["/docker-entrypoint.sh"]
