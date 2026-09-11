FROM php:8.4-cli-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    PHP_CLI_SERVER_WORKERS=4 \
    TZ=UTC

RUN apk add --no-cache \
        bash \
        curl \
        git \
        libzip-dev \
        postgresql-dev \
        tzdata \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && rm -rf /var/cache/apk/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY conf.d/dev.ini /usr/local/etc/php/conf.d/zz-opsevidence.ini
COPY init.sh /usr/local/bin/opsevidence-init
RUN chmod +x /usr/local/bin/opsevidence-init

WORKDIR /var/www/html

EXPOSE 8000
