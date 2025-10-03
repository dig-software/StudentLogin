# Simple Dockerfile for Railway / generic container hosting
FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli

# Copy application
WORKDIR /var/www/html
COPY . /var/www/html

# Harden: disable directory listing (Apache config tweak)
RUN a2enmod rewrite

# Set proper permissions for uploads (adjust if host enforces different UID)
RUN mkdir -p uploads \
 && chown -R www-data:www-data uploads \
 && chmod -R 775 uploads

# Expose port (Railway auto-detects)
EXPOSE 80

# Healthcheck (basic): try hitting login page
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/login.html || exit 1
