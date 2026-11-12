FROM composer:2 AS composer

FROM php:8.2-apache
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev unzip \
 && docker-php-ext-install pdo_mysql mbstring \
 && rm -rf /var/lib/apt/lists/* \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
COPY config/composer.json config/composer.lock /opt/flexialmacen/
RUN COMPOSER_ALLOW_SUPERUSER=1 composer --working-dir=/opt/flexialmacen install --no-dev --no-interaction --prefer-dist --no-progress
WORKDIR /var/www/html
COPY . /var/www/html
EXPOSE 80
