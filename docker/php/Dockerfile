FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_mysql pdo_sqlite redis pcntl bcmath intl zip opcache \
 && apk add --no-cache shadow git unzip \
 && groupmod -o -g ${GID} www-data \
 && usermod -o -u ${UID} -g ${GID} www-data \
 && { \
      echo "memory_limit=512M"; \
      echo "upload_max_filesize=10M"; \
      echo "post_max_size=12M"; \
      echo "opcache.validate_timestamps=1"; \
      echo "opcache.revalidate_freq=2"; \
      echo "realpath_cache_size=4096K"; \
      echo "realpath_cache_ttl=600"; \
    } > /usr/local/etc/php/conf.d/99-app.ini \
 && mkdir -p /var/www/html/vendor \
 && chown www-data:www-data /var/www/html /var/www/html/vendor

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
USER www-data

CMD ["php-fpm"]
