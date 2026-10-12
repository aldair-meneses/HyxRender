FROM php:8.3-apache

# System deps + PHP extensions WordPress needs
RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip curl libzip-dev libpng-dev libjpeg-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli zip gd opcache \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer + WP-CLI
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN curl -fsSL -o /usr/local/bin/wp \
      https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Mago PHP toolchain (lint/format/analyze), pinned + checksummed
ARG MAGO_VERSION=1.56.3
ARG MAGO_SHA256=84c35fdd4cc0eab92bcc01d8ced49036cff4b263a567e73ab1aac9f507058fe0
RUN curl -fsSL -o /tmp/mago.tar.gz \
      https://github.com/carthage-software/mago/releases/download/${MAGO_VERSION}/mago-${MAGO_VERSION}-x86_64-unknown-linux-gnu.tar.gz \
    && echo "${MAGO_SHA256}  /tmp/mago.tar.gz" | sha256sum -c - \
    && mkdir -p /tmp/mago-extract && tar -xzf /tmp/mago.tar.gz -C /tmp/mago-extract \
    && find /tmp/mago-extract -name mago -type f -exec mv {} /usr/local/bin/mago \; \
    && chmod +x /usr/local/bin/mago \
    && rm -rf /tmp/mago.tar.gz /tmp/mago-extract \
    && mago --version

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY docker/php/conf.d/opcache-dev.ini /usr/local/etc/php/conf.d/opcache-dev.ini
COPY docker/apache/bedrock.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/dev-entrypoint
RUN chmod +x /usr/local/bin/dev-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["dev-entrypoint"]
CMD ["apache2-foreground"]
