FROM php:8.3-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
        libpq-dev \
        libicu-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Environemnt variables
ENV DATABASE_URL=postgresql://bareapi:bareapi@db:5432/bareapi?serverVersion=17&charset=utf8

WORKDIR /app

# install PHP dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# copy application code
COPY . ./

# start the built-in web server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
