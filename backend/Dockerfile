FROM php:8.3-cli-bookworm

# Install system dependencies — split menjadi beberapa layer
# agar lebih toleran terhadap timeout jaringan
RUN apt-get update -qq

RUN apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip

RUN apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    libexif-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        bcmath \
        zip \
        gd \
        exif \
        intl \
        pcntl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY . .

# Run post-install scripts
RUN composer run-script post-autoload-dump

# Set permissions
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Cache config (tanpa DB — hanya config statis)
# JANGAN jalankan config:cache saat build karena env vars Railway
# baru tersedia saat runtime, bukan build time.
# Cache akan dibuat oleh start.sh setelah env terload.

EXPOSE 8080

COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
