FROM php:8.3-apache-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    libldap2-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap --with-libdir=lib/$(uname -m)-linux-gnu/ \
    && docker-php-ext-install \
        gd \
        opcache \
        intl \
        pdo_mysql \
        zip \
        ldap

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html

# Install PHP dependencies (THIS CREATES vendor/)
RUN composer install --no-dev --optimize-autoloader

#Install npm dependencies
RUN npm install && npm run build

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
