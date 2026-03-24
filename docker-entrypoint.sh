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

# Persist logs
mkdir -p /data/logs
rm -rf /app/storage/logs
ln -sf /data/logs /app/storage/logs

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

# Run the scheduler so time-based auction notifications still fire when no tab is open
php artisan schedule:work >> /app/storage/logs/scheduler.log 2>&1 &

# Serve with Octane (FrankenPHP)
WORKERS=${OCTANE_WORKERS:-auto}
# Recycle workers after N requests to prevent slow memory leaks from accumulating
# indefinitely. FrankenPHP respawns the worker transparently with no dropped requests.
MAX_REQUESTS=${OCTANE_MAX_REQUESTS:-500}
exec php artisan octane:frankenphp --host=0.0.0.0 --port=443 --workers="$WORKERS" --max-requests="$MAX_REQUESTS" --caddyfile /app/Caddyfile
