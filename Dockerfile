FROM php:8.3-apache-bullseye

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

LABEL maintainer="Andrew Lyons <andrew@nicols.co.uk>" \
    org.opencontainers.image.source="https://github.com/moodlehq/moodle-bench-diff"

ARG TARGETPLATFORM
ENV TARGETPLATFORM=${TARGETPLATFORM:-linux/amd64}

# Allow composer to run plugins during build.
# https://github.com/composer/composer/issues/11839
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN echo "Building for ${TARGETPLATFORM}"

EXPOSE 80

RUN apt-get update \
    && apt-get install -y \
        zlib1g-dev g++ git libicu-dev zip libzip-dev gnupg apt-transport-https \
        libicu-dev libonig-dev libzip-dev zip unzip \
    && curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get update \
    && apt-get install -y symfony-cli \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install zip \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install opcache \
    && apt-get purge -y --auto-remove -o APT:::AutoRemove::RecommendsImportant=false

WORKDIR /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN symfony check:requirements

COPY docker/entrypoint.sh /entrypoint.sh
COPY application /var/www

RUN composer install -n \
    && rm -rf /root/.composer

CMD ["symfony", "server:start", "--port=80", "--no-tls", "--allow-http"]
ENTRYPOINT ["/entrypoint.sh"]
