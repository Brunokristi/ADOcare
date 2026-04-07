FROM php:8.4-cli AS builder

# Install system dependencies required for Composer and Node
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    ca-certificates \
    build-essential \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20 (for building frontend assets)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Optimize build cache: install PHP deps first by copying composer files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts || true

# Install frontend deps and build (if package.json present)
COPY package.json package-lock.json* ./
RUN if [ -f package.json ]; then npm ci && npm run build || true; fi

# Copy application source and finalize build artifacts
COPY . /app
RUN composer dump-autoload --optimize || true

FROM php:8.4-fpm AS runtime

# System packages and PHP extensions for runtime
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath gd intl || true \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy built vendor and public assets from builder
COPY --from=builder /app /var/www/html

# Set permissions for storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
