# syntax=docker/dockerfile:1

###############################################################################
# Unwrap24 API — production image for Google Cloud Run
#
# Multi-stage build:
#   * vendor  — resolves production Composer dependencies (no dev packages)
#   * runtime — PHP 8.3 FPM + nginx + supervisor, listening on $PORT
#
# Never bake .env or credentials into the image: configuration is injected at
# runtime by Cloud Run (plain env vars + Secret Manager references).
###############################################################################

ARG PHP_VERSION=8.3

# -----------------------------------------------------------------------------
# Stage 1: Composer dependencies (production only)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# Platform requirements are checked again in the runtime stage, which actually
# has the PHP extensions installed.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .

RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --classmap-authoritative \
        --no-scripts

# -----------------------------------------------------------------------------
# Stage 2: Runtime (PHP-FPM + nginx, Cloud Run compatible)
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
        curl \
        gettext \
        nginx \
        supervisor \
        tzdata \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -f /usr/local/bin/install-php-extensions \
    && rm -rf /var/cache/apk/* /tmp/*

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

# The host's package/config caches reference dev-only packages (e.g. Laravel
# Boost) that are not installed in the production image. Regenerate them at
# runtime, after Cloud Run injects its configuration.
RUN rm -f bootstrap/cache/*.php \
    && mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-unwrap24.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-unwrap24-opcache.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-unwrap24.conf
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

# Cloud Run does not use HEALTHCHECK (it uses startup/liveness probes hitting
# Laravel's /up health endpoint), but this keeps `docker run` usable locally.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
