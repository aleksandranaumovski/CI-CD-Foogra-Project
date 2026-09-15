####################################################################
# Foogra API — backend-only image for the split deployment.
#
# The repository's root Dockerfile bakes the React SPA into Laravel's
# public/ folder for a single-container Render deploy. This Dockerfile
# is the multi-service counterpart used by docker-compose.yml and k8s/:
# it builds and serves the Laravel API alone (php-fpm + nginx under
# supervisor, same as the combined image), while docker/frontend.Dockerfile
# builds a separate nginx container for the SPA.
####################################################################

# ---------------------------------------------------------------- #
# 1. Install PHP dependencies.                                     #
# ---------------------------------------------------------------- #
FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./

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
# 2. Runtime: PHP-FPM + nginx under supervisor.                    #
# ---------------------------------------------------------------- #
# 8.4 rather than 8.3: config/database.php references the namespaced
# Pdo\Mysql class, which only exists from PHP 8.4 onwards.
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
      nginx \
      supervisor \
      icu-libs \
      libzip \
 && apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS \
      icu-dev \
      libzip-dev \
 && docker-php-ext-install -j"$(nproc)" \
      bcmath \
      intl \
      opcache \
      pcntl \
      pdo_mysql \
      zip \
 && apk del --no-network .build-deps \
 && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/zz-foogra.ini
COPY docker/php/www.conf    /usr/local/etc/php-fpm.d/zz-foogra.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/api/nginx.conf   /etc/nginx/nginx.conf

COPY --from=vendor /app /var/www/html

COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

RUN mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/framework/testing \
      storage/logs \
      storage/app/public \
      bootstrap/cache \
      /run/nginx \
 && chown -R www-data:www-data storage bootstrap/cache /run/nginx \
 && chmod -R 775 storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
