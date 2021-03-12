ARG NAMESPACE
ARG PHP_VERSION

FROM ${NAMESPACE}:${PHP_VERSION}-cli

RUN apt-get update && \
    apt-get install -y \
        apt-utils \
        libxml2-dev \
        curl \
        zlib1g-dev \
        libicu-dev \
        git \
        g++ \
        unzip \
        libzip-dev \
        zip \
        libtool \
        make \
        build-essential \
        automake \
        ca-certificates && \
    apt-get autoremove -y && \
    apt-get clean -y && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN docker-php-ext-install opcache bcmath zip pcntl intl

RUN docker-php-ext-configure zip

RUN { \
        echo "short_open_tag=off"; \
        echo "date.timezone=Europe/Berlin"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "realpath_cache_size=4096K"; \
        echo "realpath_cache_ttl=600"; \
        echo "error_reporting = E_ALL"; \
        echo "display_startup_errors = On"; \
        echo "ignore_repeated_errors = Off"; \
        echo "ignore_repeated_source = Off"; \
        echo "html_errors = On"; \
        echo "display_errors = On"; \
        echo "log_errors = On"; \
        echo "error_log = /var/log/php/cli-error.log"; \
    } > /usr/local/etc/php/php.ini

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer && \
    composer global require phploc/phploc ergebnis/composer-normalize

ARG INSTALL_XDEBUG
ARG XDEBUG_VERSION

RUN if [ ${INSTALL_XDEBUG} = true ]; then pecl install "xdebug-${XDEBUG_VERSION}" ;fi
RUN if [ ${INSTALL_XDEBUG} = true ]; then docker-php-ext-enable xdebug ;fi

RUN { \
        echo 'xdebug.idekey=PHPSTORM'; \
        echo 'xdebug.remote_port=9003'; \
        echo 'xdebug.discover_client_host=true'; \
        echo 'xdebug.client_host=host.docker.internal'; \
        echo 'xdebug.start_with_request=yes'; \
        echo 'xdebug.profiler_output_dir="/var/log/xdebug"'; \
        echo 'xdebug.cli_color=1'; \
        echo 'xdebug.mode=profile,develop,coverage'; \
        echo 'xdebug.max_nesting_level=1000'; \
    } > /usr/local/etc/php/conf.d/php-ext-xdebug.ini

RUN mkdir /var/log/php && touch /var/log/php/cli-error.log && chmod 0664 /var/log/php/cli-error.log

WORKDIR /var/www/package

ENV PATH="/root/.composer/vendor/bin:${PATH}"
ENV DOKCER_RUN=true
