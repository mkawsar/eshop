# Magento 2.4.7 - PHP 8.2 FPM
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libxml2-dev \
    icu-dev \
    oniguruma-dev \
    libxslt-dev \
    freetype-dev \
    libwebp-dev \
    gmp-dev \
    linux-headers \
    $PHPIZE_DEPS

# Configure and install PHP extensions for Magento 2.4
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    bcmath \
    gd \
    intl \
    pdo_mysql \
    soap \
    xsl \
    zip \
    opcache \
    sockets \
    gmp

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# PHP settings for Magento
RUN echo "memory_limit=2G" > /usr/local/etc/php/conf.d/magento.ini \
    && echo "max_execution_time=18000" >> /usr/local/etc/php/conf.d/magento.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/magento.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/magento.ini

# Create app user (optional, run as root for simplicity in dev)
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
