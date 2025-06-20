###
# Custom FrankenPHP image with PostgreSQL PDO extension
###
FROM dunglas/frankenphp:php8.4

# Switch to root to install system dependencies
USER root

# Install PostgreSQL client dev files and build PDO PGSQL
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*