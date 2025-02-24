FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    curl \
    zip \
    unzip \
    git \
    libicu-dev \
    libpq-dev \
    && docker-php-ext-install intl pdo pdo_mysql opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer self-update

# Set working directory
WORKDIR /var/www/html

# Copy existing project files
COPY . .

# Install Redis extension for PHP
RUN apt-get update && apt-get install -y libz-dev \
    && pecl install redis \
    && docker-php-ext-enable redis

CMD ["php-fpm"]
