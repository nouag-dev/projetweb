FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql
RUN printf "upload_max_filesize=5M\npost_max_size=30M\nmax_file_uploads=5\n" > /usr/local/etc/php/conf.d/uploads.ini

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/src/

RUN chown -R www-data:www-data /var/www/html
