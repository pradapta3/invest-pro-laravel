#!/bin/sh
#
# Container entrypoint. One image serves three roles; CONTAINER_ROLE picks
# which. Everything before the dispatch at the bottom runs for all of them.
#
set -eu

APP_DIR=/var/www/html
ROLE="${CONTAINER_ROLE:-app}"

cd "$APP_DIR"

log() { printf '[entrypoint:%s] %s\n' "$ROLE" "$*" >&2; }
die() { printf '[entrypoint:%s] FATAL: %s\n' "$ROLE" "$*" >&2; exit 1; }

# Read a key out of the bind-mounted .env. The shell needs DB_HOST/DB_PORT to
# wait on MySQL, and duplicating them into docker-compose `environment:` would
# just create a second place for them to drift out of sync.
env_get() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" .env 2>/dev/null \
        | tail -n1 \
        | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/" \
        | tr -d '\r'
}

# -----------------------------------------------------------------------------
# storage/ skeleton
#
# The named volume mounted at storage/ shadows whatever the image built there,
# so on a brand-new volume these directories do not exist and Laravel fails on
# the first write. Recreate them every boot — it is cheap and idempotent.
# -----------------------------------------------------------------------------
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# -----------------------------------------------------------------------------
# Configuration sanity checks — fail loudly at boot rather than with a 500 on
# the first request.
# -----------------------------------------------------------------------------
[ -f .env ] || die ".env is missing. Mount it at $APP_DIR/.env (see docker-compose.yml)."

APP_KEY_VALUE="$(env_get APP_KEY)"
[ -n "$APP_KEY_VALUE" ] || die "APP_KEY is empty. Run: php artisan key:generate on the host, or ./deploy.sh which does it for you."

APP_DEBUG_VALUE="$(env_get APP_DEBUG)"
case "$APP_DEBUG_VALUE" in
    true|True|TRUE|1)
        log "WARNING: APP_DEBUG is enabled. Stack traces will be shown to visitors — set APP_DEBUG=false."
        ;;
esac

# -----------------------------------------------------------------------------
# Wait for MySQL. depends_on/service_healthy already covers the normal path;
# this catches restarts where MySQL is briefly gone and keeps the container
# from crash-looping through its restart budget.
# -----------------------------------------------------------------------------
DB_HOST="$(env_get DB_HOST)"
DB_PORT="$(env_get DB_PORT)"
: "${DB_HOST:=mysql}"
: "${DB_PORT:=3306}"

# fsockopen rather than `nc -z`: BusyBox's nc is built without -z on Alpine,
# and PHP is the one interpreter this image is guaranteed to have.
db_is_up() {
    php -r '$s=@fsockopen($argv[1],(int)$argv[2],$e,$m,2); if(!$s){exit(1);} fclose($s); exit(0);' \
        "$DB_HOST" "$DB_PORT" 2>/dev/null
}

log "waiting for database at ${DB_HOST}:${DB_PORT} ..."
attempt=0
until db_is_up; do
    attempt=$((attempt + 1))
    [ "$attempt" -ge 60 ] && die "database at ${DB_HOST}:${DB_PORT} unreachable after 60 attempts."
    sleep 2
done
log "database is reachable."

# -----------------------------------------------------------------------------
# An explicit command (docker compose run app php artisan ...) means the caller
# wants a one-off task, not a boot sequence. Hand over before migrating.
# -----------------------------------------------------------------------------
if [ "$#" -gt 0 ]; then
    log "running one-off command: $*"
    exec "$@"
fi

# Package discovery is skipped during the image build (no .env there yet), so
# it happens once per container start instead.
php artisan package:discover --ansi >/dev/null

# -----------------------------------------------------------------------------
# Schema. Only the web container migrates, so three containers coming up at
# once cannot race each other through the same migration.
# -----------------------------------------------------------------------------
if [ "$ROLE" = "app" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "running database migrations ..."
    php artisan migrate --force --no-interaction
fi

if [ "$ROLE" = "app" ]; then
    php artisan storage:link || log "storage:link skipped (already present)."
fi

# -----------------------------------------------------------------------------
# Warm the caches. These three land in bootstrap/cache, which lives in the
# image layer rather than a shared volume, so each role caches into its own
# copy and they cannot collide.
# -----------------------------------------------------------------------------
log "caching config, routes and events ..."
php artisan config:cache
php artisan route:cache
php artisan event:cache

# Compiled Blade templates are the exception: they go to storage/framework/views,
# which all three roles share. Only the web container precompiles them, so the
# queue and scheduler are not racing it to write byte-identical files.
if [ "$ROLE" = "app" ]; then
    php artisan view:cache
fi

# The steps above ran as root, so the files they wrote are root-owned. Hand
# them back to www-data, otherwise anything Laravel decides to regenerate at
# request time fails with a permission error under php-fpm.
chown -R www-data:www-data bootstrap/cache storage

case "$ROLE" in
    app)
        log "starting nginx + php-fpm."
        exec supervisord -c /etc/supervisor/supervisord.conf
        ;;

    queue)
        # --max-time recycles the worker hourly so a slow leak in a long-lived
        # PHP process never becomes the reason the queue stalls.
        log "starting queue worker."
        exec php artisan queue:work \
            --queue=default \
            --sleep=3 \
            --tries=3 \
            --backoff=10 \
            --timeout=300 \
            --max-time=3600 \
            --no-interaction
        ;;

    scheduler)
        # schedule:work is the long-running equivalent of a `* * * * *` cron
        # entry calling schedule:run — see routes/console.php for the cadences.
        log "starting scheduler."
        exec php artisan schedule:work --no-interaction
        ;;

    *)
        die "unknown CONTAINER_ROLE '$ROLE' (expected: app, queue or scheduler)."
        ;;
esac
