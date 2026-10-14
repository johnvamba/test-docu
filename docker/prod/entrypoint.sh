#!/bin/sh
# Prepares runtime config, then hands over to supervisord.
# Every default can be overridden by setting the variable on the host.
set -eu
cd /var/www/html

export PORT="${PORT:-8080}"

# Public URL. Render provides RENDER_EXTERNAL_HOSTNAME, Railway provides RAILWAY_PUBLIC_DOMAIN;
# other hosts should set APP_URL.
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
  export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
elif [ -z "${APP_URL:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
export APP_URL="${APP_URL:-http://localhost:${PORT}}"
PUBLIC_HOST=$(echo "$APP_URL" | sed -E 's#^https?://##; s#/.*$##')
case "$APP_URL" in https://*) PUBLIC_SCHEME=https; PUBLIC_PORT=443 ;; *) PUBLIC_SCHEME=http; PUBLIC_PORT="${PORT}" ;; esac

export SANCTUM_STATEFUL_DOMAINS="${SANCTUM_STATEFUL_DOMAINS:-$PUBLIC_HOST}"
export SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-$([ "$PUBLIC_SCHEME" = https ] && echo true || echo false)}"

# Secrets that are generated once per container when not provided.
SECRETS=storage/app/.runtime-secrets
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
[ -f "$SECRETS" ] || {
  printf 'GEN_APP_KEY=base64:%s\nGEN_REVERB_KEY=%s\nGEN_REVERB_SECRET=%s\n' \
    "$(php -r 'echo base64_encode(random_bytes(32));')" \
    "$(php -r 'echo bin2hex(random_bytes(10));')" \
    "$(php -r 'echo bin2hex(random_bytes(20));')" > "$SECRETS"
}
. "./$SECRETS"
export APP_KEY="${APP_KEY:-$GEN_APP_KEY}"
export REVERB_APP_ID="${REVERB_APP_ID:-100001}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-$GEN_REVERB_KEY}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-$GEN_REVERB_SECRET}"

# Reverb runs inside this container on 8081; nginx proxies /app/* to it.
export BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-reverb}"
export REVERB_HOST="${REVERB_HOST:-127.0.0.1}" REVERB_PORT="${REVERB_PORT:-8081}" REVERB_SCHEME="${REVERB_SCHEME:-http}"
export REVERB_PUBLIC_HOST="${REVERB_PUBLIC_HOST:-$PUBLIC_HOST}"
export REVERB_PUBLIC_PORT="${REVERB_PUBLIC_PORT:-$PUBLIC_PORT}"
export REVERB_PUBLIC_SCHEME="${REVERB_PUBLIC_SCHEME:-$PUBLIC_SCHEME}"

# SQLite by default: no database service to provision.
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
if [ "$DB_CONNECTION" = sqlite ]; then
  export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
  [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi
export SESSION_DRIVER="${SESSION_DRIVER:-database}" CACHE_STORE="${CACHE_STORE:-database}" QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export APP_ENV="${APP_ENV:-production}" APP_DEBUG="${APP_DEBUG:-false}" LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

php artisan migrate --force
# The seeder is idempotent: it (re)creates the three reviewer accounts and their sample documents.
if [ "${SEED_DEMO_DATA:-true}" = true ]; then php artisan db:seed --force; fi
php artisan optimize

chown -R www-data:www-data storage bootstrap/cache
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

exec "$@"
