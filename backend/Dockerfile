FROM php:8.4-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev unzip git \
    && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql pdo_sqlite bcmath exif pcntl zip intl opcache gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
# --no-reload is required for PHP_CLI_SERVER_WORKERS (.env) to actually take
# effect — without it, `artisan serve` silently falls back to a single
# worker, which deadlocks any request that (like /qa/image-test) blocks on
# a call to another service that calls back into this same backend.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
