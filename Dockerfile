FROM php:8.2-fpm
RUN touch /var/log/logs 

# Arguments defined in docker-compose.yml
ARG user
ARG uid
RUN cd /var/www
# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Crie o diretório para o socket PHP
RUN mkdir -p /var/run/php \
    && chown www-data:www-data /var/run/php \
    && chmod 755 /var/run/php

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Crie o diretório para o socket
RUN mkdir -p /var/run/php && chown www-data:www-data /var/run/php

# Get latest and install  Composer
COPY . .
COPY /config/nginx.conf /etc/nginx/nginx.conf
COPY /config/fastcgi-php.conf /etc/nginx/snippets/

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 
#RUN export COMPOSER_ALLOW_SUPERUSER=1 

   
# # Create system user to run Composer and Artisan Commands
RUN chmod 7777 -R /var/www
RUN mkdir /home/$user
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN chown -R $user:$user /home/$user

RUN chown -R $user:$user /var/www \
    && chmod -R 775 /var/www

USER $user
RUN cd /var/www

RUN composer install 

EXPOSE 9000

CMD ["php-fpm"]