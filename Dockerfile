#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions \
    redis \
    intl \
    opcache \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV SERVER_NAME=:80
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY bin bin
COPY config config
COPY public public
COPY src src
COPY .env .env

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer run-script --no-dev post-install-cmd \
    && mkdir -p var/log \
    && chown -R www-data:www-data var

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
