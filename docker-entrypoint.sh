#!/bin/sh
set -e

# Create SQLite database if it doesn't exist
if [ ! -f /data/database.sqlite ]; then
    touch /data/database.sqlite
fi

# Persist uploaded images
mkdir -p /data/images
rm -rf /app/storage/app/public
ln -sf /data/images /app/storage/app/public

# Persist Caddy TLS certificates
export XDG_DATA_HOME=/data/caddy_data
export XDG_CONFIG_HOME=/data/caddy_config

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php artisan key:generate --show)
fi

# Ensure config cache reflects runtime env (APP_KEY, secrets, etc.)
php artisan config:clear
php artisan config:cache

# Run migrations
php artisan migrate --force

# Serve with Octane (FrankenPHP)
exec php artisan octane:frankenphp --host=0.0.0.0 --port=443 --admin-port=2019 --caddyfile /app/Caddyfile
