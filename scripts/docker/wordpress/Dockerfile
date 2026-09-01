# The running site. The official WordPress image already provides Apache + PHP + WP, so
# this only adds Xdebug for step-debugging the plugin. Xdebug stays off unless XDEBUG_MODE
# is set (e.g. XDEBUG_MODE=debug ./scripts/dev up), so day-to-day requests are not slowed.
ARG WORDPRESS_IMAGE=wordpress:7.0-php8.3-apache
FROM ${WORDPRESS_IMAGE}

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && { \
        echo "xdebug.mode=\${XDEBUG_MODE}"; \
        echo "xdebug.start_with_request=yes"; \
        echo "xdebug.client_host=host.docker.internal"; \
        echo "xdebug.discover_client_host=1"; \
    } > /usr/local/etc/php/conf.d/zz-xdebug.ini
ENV XDEBUG_MODE=off
