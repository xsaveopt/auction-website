#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_ENV=local
export APP_DEBUG=true
export APP_KEY="base64:+GSxloMI5yE3aue4kc+Wkh6joZ8KfhqVW5Hk0+pwMaM="
export DB_CONNECTION=sqlite
export DB_DATABASE="$(pwd)/database/e2e.sqlite"
export SESSION_DRIVER=file
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export MICROSOFT_CLIENT_ID=""
export MICROSOFT_CLIENT_SECRET=""

pnpm build

: > "$DB_DATABASE"
php artisan migrate:fresh --force
php artisan app:create-admin e2eadmin password123 || true

cd public
exec php -S 127.0.0.1:8123 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
