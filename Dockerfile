# Backroom on Render: PHP 8.3 with Apache. Only public/ is the web root.
# Render builds this file itself (see README, "Deploy to Render").
FROM php:8.3.36-apache

# PHP extensions from CLAUDE.md: pdo_pgsql, gd (JPEG, PNG, WebP) and exif.
# fileinfo and mbstring are already part of the official image.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd exif \
 && rm -rf /var/lib/apt/lists/*

# php.ini: the production defaults plus Backroom's limits (docker/php.ini).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/backroom.ini"

# Apache: document root public/, and listen on the port Render gives in $PORT
# (Apache reads ${PORT} from the environment when it starts; 10000 if not set).
ENV PORT=10000
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf \
 && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf
COPY docker/apache.conf /etc/apache2/conf-available/backroom.conf
RUN a2enconf backroom

# The app. Composer is left out on purpose: the app has no runtime packages (PHPUnit is a
# dev package only) and the pages load src/ directly. When a runtime package is added to
# composer.json "require", add a `composer install --no-dev` step here.
COPY --chown=www-data:www-data . /var/www/html

EXPOSE 10000
# The official image starts Apache in the foreground (apache2-foreground).
