FROM php:8.1-apache

WORKDIR /var/www/html

# Install system dependencies + Node 18
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libldap2-dev \
    libsasl2-dev \
    zip \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g yarn \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap --with-libdir=lib/$(uname -m)-linux-gnu/ \
    && docker-php-ext-install pdo_mysql gd intl zip ldap \
    && rm -rf /var/lib/apt/lists/*

# Enable apache rewrite
RUN a2enmod rewrite

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application source
COPY . .

# Install backend dependencies
WORKDIR /var/www/html/src
RUN composer install --no-dev --optimize-autoloader

# Build main frontend
WORKDIR /var/www/html/src/client
RUN yarn install && yarn build

# Build installer frontend
WORKDIR /var/www/html/installer/client
RUN yarn install && yarn build

# Fix permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
