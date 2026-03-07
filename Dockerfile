FROM php:8.4.18-cli-alpine3.23@sha256:3f73115e52c619f12e54bb9cf19eff97e817c1faabd787c0de1caf5be6f913f6 AS base

RUN apk add --no-cache \
    curl-dev \
    libxml2-dev \
  && docker-php-ext-install \
    curl \
    xml \
  && apk del libxml2-dev curl-dev

COPY --from=composer:2.8.4@sha256:72b9e4b2038558f7256e7f925495aa791c0ee764e1d351f3963b379b18b4c2f5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./

# --- QA: dev dependencies included ---
FROM base AS qa

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

# --- Production: no dev dependencies ---
FROM base AS prod

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

COPY . .

ENTRYPOINT ["php"]
CMD ["bin/pipeline.php"]
