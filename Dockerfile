# Multi-stage build for production
FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    build-base \
    autoconf \
    linux-headers \
    libzip-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    curl-dev \
    libxml2-dev \
    git \
    unzip \
    nodejs \
    npm \
    supervisor \
    nginx

# Install V8 and V8Js
RUN apk add --no-cache v8-dev
RUN pecl install v8js && docker-php-ext-enable v8js

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) \
    gd \
    intl \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    opcache \
    bcmath \
    sockets \
    pcntl \
    xml \
    curl \
    mbstring

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Set permissions
RUN chown -R www:www /var/www && \
    chmod -R 755 /var/www/storage && \
    chmod -R 755 /var/www/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Install Node.js dependencies and build assets
RUN npm install --production && npm run build

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Configure Nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Configure Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories
RUN mkdir -p /var/log/supervisor && \
    mkdir -p /var/run/nginx && \
    mkdir -p /var/run/php

# Switch to application user
USER www

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Production stage
FROM base AS production

# Additional production optimizations
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Development stage
FROM base AS development

# Install development dependencies
RUN composer install --optimize-autoloader

# Install Xdebug for development
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Don't cache configurations in development
RUN php artisan config:clear