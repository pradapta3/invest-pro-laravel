# syntax=docker/dockerfile:1

# =============================================================================
# Dompet Ijo (IDX Invest) — production image
#
# One image, three roles. CONTAINER_ROLE (read by docker/entrypoint.sh)
# selects between the web front end (nginx + php-fpm under supervisor), the
# queue worker, and the scheduler, so all three always run byte-identical
# code and there is only ever one thing to build and one thing to push.
# =============================================================================


# -----------------------------------------------------------------------------
# Stage 1 — front-end assets
#
# The Blade layouts fall back to the Tailwind CDN when
# public/build/manifest.json is missing (see resources/views/layouts/*.blade.php).
# That fallback is a development convenience — it pulls a JIT compiler into the
# browser on every page load — so production must ship a real Vite build.
# -----------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

# `npm ci` off the committed lockfile, so two builds of the same commit produce
# the same bundle. The glob and the fallback keep this working if the lockfile
# is ever removed.
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources

RUN npm run build


# -----------------------------------------------------------------------------
# Stage 2 — PHP dependencies
#
# Built on the same PHP version as the runtime stage, with the composer binary
# copied in, rather than on the `composer:2` image: that image tracks whatever
# PHP release is current, and resolving dependencies against a different PHP
# than the one that will run them is how you get a platform-requirement failure
# on an otherwise unchanged build.
#
# Dependencies are installed before the app code is copied in so that a change
# to app/ or resources/ does not invalidate the (slow) composer layer.
# -----------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git + unzip are how composer unpacks dist packages; without them it falls back
# to cloning every dependency, which is far slower.
RUN apk add --no-cache git unzip

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: post-autoload-dump runs `artisan package:discover`, which needs
# the application code that has not been copied in yet. Discovery happens at
# container start instead (entrypoint), where a real .env is present.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

# --no-scripts again for the same reason: post-autoload-dump would fire
# `artisan package:discover`, and this stage has neither a .env nor pdo_mysql.
# Discovery runs once per container start instead (docker/entrypoint.sh).
#
# --optimize but not --classmap-authoritative: the classmap still needs a PSR-4
# fallback, otherwise anything not present at build time is a fatal
# class-not-found that only ever shows up in production.
RUN composer dump-autoload --no-dev --optimize --no-scripts


# -----------------------------------------------------------------------------
# Stage 3 — runtime
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

# Extensions:
#   pdo_mysql — the app's only database driver (config/database.php)
#   opcache   — mandatory for any serious PHP throughput
#   pcntl     — lets queue:work and schedule:work handle SIGTERM, so
#               `docker compose down` drains instead of killing mid-job
#
# Everything else Laravel needs (mbstring, curl, dom/simplexml for the RSS
# feeds, openssl, tokenizer, fileinfo) is already compiled into the base image.
#
# $PHPIZE_DEPS is the compiler toolchain docker-php-ext-install requires. The
# alpine images define the variable but deliberately do not install it, so it
# has to be added and then removed again — leaving it in roughly doubles the
# image size. It is unquoted on purpose: it is a space-separated package list
# that has to word-split (shellcheck flags this as SC2086; quoting it would
# make apk look for one absurdly long package name).
RUN set -eux; \
    apk add --no-cache nginx supervisor curl tzdata; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache pcntl; \
    apk del --no-network .build-deps; \
    rm -rf /var/cache/apk/* /tmp/*

WORKDIR /var/www/html

COPY docker/php/php.ini      /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /run/nginx /var/log/supervisor

# Application code, then the two build stages' output on top.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor       ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# storage/ is replaced by a named volume at runtime; the entrypoint rebuilds
# the directory skeleton there. bootstrap/cache stays in the image layer, so
# each deploy starts from a clean config/route/view cache.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
