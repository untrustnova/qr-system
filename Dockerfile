# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:php8.4-alpine

# Label for metadata
LABEL maintainer="QR Absence System"

# 1. Install System Dependencies & PHP Extensions
# Added: redis (critical), zip, gd, intl, opcache, bcmath
RUN install-php-extensions \
    pcntl \
    pdo_mysql \
    pdo_sqlite \
    redis \
    zip \
    gd \
    intl \
    opcache \
    bcmath

# 2. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Install Node.js & Bun (for lightweight frontend builds inside container if needed)
RUN apk add --no-cache nodejs npm bash

# 4. Configure PHP for Octane/Production
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/conf.d"

# Optimization: OPcache config
RUN echo "opcache.enable=1" >> $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.enable_cli=1" >> $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=128" >> $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> $PHP_INI_DIR/conf.d/docker-php-ext-opcache.ini

# 5. Create User (match typical host UID/GID to fix permission issues)
ARG WWWUSER=1000
ARG WWWGROUP=1000

RUN addgroup -g $WWWGROUP sail && \
    adduser -D -u $WWWUSER -G sail sail

# 6. Set Working Directory
WORKDIR /app

# 7. Entrypoint
ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]