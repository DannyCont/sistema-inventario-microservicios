FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
WORKDIR /var/www/html
COPY . /var/www/html
EXPOSE 80