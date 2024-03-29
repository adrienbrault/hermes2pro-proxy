FROM dunglas/frankenphp

RUN set -eux; \
    install-php-extensions \
        @composer \
        bcmath \
        intl \
    ;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV SERVER_NAME=":80"

COPY composer.json /app/

RUN composer install && composer require symfony/http-foundation:^7.0

COPY . /app/
