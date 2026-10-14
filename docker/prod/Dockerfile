# Single-container production image: nginx + php-fpm + queue worker + Reverb,
# supervised together. SQLite by default so a free-tier host needs no add-ons.
# Build from the repo root:  docker build -f docker/prod/Dockerfile -t ajaia-docs .

# ---- 1. Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.ts tsconfig.json ./
COPY resources ./resources
RUN npm run build

# ---- 2. PHP dependencies ------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- 3. Runtime ---------------------------------------------------------------
FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_sqlite pdo_mysql intl zip opcache pcntl \
 && apk add --no-cache nginx supervisor gettext \
 && mkdir -p /run/nginx

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/prod/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN rm -f public/hot \
 && chown -R www-data:www-data storage bootstrap/cache

ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
