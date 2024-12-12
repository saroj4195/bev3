# Use a PHP 8.1 base image
FROM php:8.1-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libzip-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port if needed (optional, e.g., for PHP development server)
EXPOSE 8000

# Command to run the application
CMD ["php", "-S", "0.0.0.0:8000", "-t", "."]
