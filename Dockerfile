FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    curl-dev \
    libxml2-dev \
  && docker-php-ext-install \
    curl \
    xml \
  && apk del libxml2-dev curl-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/memory.ini

WORKDIR /app
