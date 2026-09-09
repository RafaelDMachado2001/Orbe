# syntax=docker/dockerfile:1
FROM php:8.3-fpm-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    bcmath \
    intl \
    zip \
    gd \
    opcache \
    pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && apt-get upgrade -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Alinha o usuario do container com o usuario do host para evitar arquivos root no bind mount.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data \
    && usermod  -o -u "${UID}" -g "${GID}" -d /var/www www-data \
    && mkdir -p /var/www/.composer \
    && chown -R www-data:www-data /var/www

COPY docker/php/php.ini      /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
