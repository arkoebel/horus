FROM php:8-apache
RUN apt-get update && apt-get install -y \
    && apt install -y libxml2-dev \
    && apt install -y librdkafka-dev \
    && docker-php-ext-configure soap \
    && docker-php-ext-configure bcmath \
    && docker-php-ext-configure sockets \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install sockets \
    && docker-php-ext-install soap \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite
COPY *php *js *xsd *html /var/www/html/
COPY vendor /var/www/html/vendor/
COPY ui /var/www/html/ui/
COPY lib /var/www/html/lib/
COPY xsd /var/www/html/xsd
COPY conf /var/www/html/conf/
COPY templates /var/www/html/templates/

