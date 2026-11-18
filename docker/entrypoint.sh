#!/bin/sh

set -e

# Cloud Run injects PORT (default 8080). nginx cannot read environment
# variables itself, so render the template at container start.
: "${PORT:=8080}"
export PORT

envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Cloud Run's filesystem is ephemeral: make sure writable paths exist on every
# cold start (storage/logs is intentionally not persisted across revisions).
mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data storage bootstrap/cache

# Cache configuration after runtime env vars (including Secret Manager
# references) are present. Route caching is skipped because the application
# defines closure routes that cannot be serialized.
php artisan config:cache

# Optional, opt-in migrations. Prefer running migrations as a separate step
# (Cloud Run job or Cloud SQL Auth Proxy) rather than on every cold start.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
