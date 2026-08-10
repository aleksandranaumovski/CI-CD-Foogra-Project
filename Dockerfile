####################################################################
# Foogra — single-image build for Render (or any Docker host).
#
# nginx serves the built React SPA and hands /api, /sanctum, /storage
# and /docs to Laravel over PHP-FPM, so the whole app lives behind one
# origin. That keeps the SPA on a relative /api/v1 base URL — no CORS
# preflight, no cross-site cookie juggling for Sanctum.
####################################################################

# ---------------------------------------------------------------- #
# 1. Build the SPA.                                                #
# ---------------------------------------------------------------- #
FROM node:22-alpine AS frontend

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

# Left empty on purpose: with the SPA and the API on the same origin the
# axios client falls back to a relative "/api/v1". Override at build time
# (--build-arg VITE_API_URL=https://api.example.com) only when splitting
# the two across separate hosts.
ARG VITE_API_URL=""
ENV VITE_API_URL=${VITE_API_URL}

RUN npm run build

# ---------------------------------------------------------------- #
# 2. Install PHP dependencies.                                     #
# ---------------------------------------------------------------- #
FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./

# --no-scripts because artisan isn't copied in yet; the autoloader is
# dumped again below once the full source is present.
RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --no-scripts \
      --no-autoloader

COPY backend/ ./

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------- #
# 3. Runtime: PHP-FPM + nginx under supervisor.                    #
# ---------------------------------------------------------------- #
# 8.4 rather than 8.3: config/database.php references the namespaced
# Pdo\Mysql class, which only exists from PHP 8.4 onwards.
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
      nginx \
      supervisor \
      libpq \
      icu-libs \
      libzip \
 && apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      postgresql-dev \
      icu-dev \
      libzip-dev \
 && docker-php-ext-install -j"$(nproc)" \
      bcmath \
      intl \
      opcache \
      pcntl \
      pdo_mysql \
      pdo_pgsql \
      zip \
 && apk del --no-network .build-deps \
 && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/zz-foogra.ini
COPY docker/php/www.conf    /usr/local/etc/php-fpm.d/zz-foogra.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template

# Laravel application + vendor tree.
COPY --from=vendor /app /var/www/html

# The SPA build lands in Laravel's public root: nginx resolves static
# files there first and only falls through to index.php for API paths.
COPY --from=frontend /app/dist/ /var/www/html/public/

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

RUN mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/framework/testing \
      storage/logs \
      storage/app/public \
      bootstrap/cache \
      /var/lib/nginx/tmp \
      /run/nginx \
 && chown -R www-data:www-data storage bootstrap/cache /var/lib/nginx /run/nginx \
 && chmod -R 775 storage bootstrap/cache

# Render injects PORT; this is only the local default.
ENV PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
