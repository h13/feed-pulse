FROM php:8.4.18-cli-alpine3.23

RUN apk add --no-cache \
    curl-dev \
    libxml2-dev \
  && docker-php-ext-install \
    curl \
    xml \
  && apk del libxml2-dev curl-dev

COPY --from=composer:2.8.4 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

COPY . .

ENTRYPOINT ["php"]
CMD ["bin/pipeline.php"]
