# The "tools" image: one PHP CLI environment that serves Composer, PHP_CodeSniffer,
# PHPStan, PHPUnit, php-scoper and WP-CLI-free test runs. No official image bundles all
# of these, so this is the single image the project builds; everything else uses stock
# images. PHP 8.3 runs the tooling (php-scoper needs >= 8.2) while the plugin itself
# still targets 8.0 — the analyzed/tested target version is set in phpcs.xml, phpstan.neon
# and phpunit, independent of the interpreter running the tools.
FROM php:8.3-cli-bookworm

# System packages: git + unzip for Composer dist installs, curl for downloads, a MySQL
# client, and headers for the zip extension.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        default-mysql-client \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions: mysqli for WP-CLI against the site database, zip for Composer, pcov for fast
# coverage, and xdebug for step debugging (kept off unless XDEBUG_MODE is set at run time).
RUN docker-php-ext-install -j"$(nproc)" mysqli zip \
    && pecl install pcov xdebug \
    && docker-php-ext-enable pcov xdebug \
    && echo "xdebug.mode=\${XDEBUG_MODE}" > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/zz-xdebug-mode.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/zz-xdebug-mode.ini
ENV XDEBUG_MODE=off

# Composer, taken from the official image so the version is pinned and reproducible.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/composer \
    COMPOSER_CACHE_DIR=/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

# php-scoper as a pinned PHAR (not a Composer dependency — it drags a PHP-Parser version
# that fights PHPStan's when installed in the same tree).
ARG PHP_SCOPER_VERSION=0.18.19
RUN curl -fsSL "https://github.com/humbug/php-scoper/releases/download/${PHP_SCOPER_VERSION}/php-scoper.phar" \
        -o /usr/local/bin/php-scoper \
    && chmod +x /usr/local/bin/php-scoper

WORKDIR /app
CMD ["php", "-v"]
