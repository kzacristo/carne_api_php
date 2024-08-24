FROM php:8.2-apache

# Configurar o vhost
COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Habilitar o mod_rewrite
RUN a2enmod rewrite

# Copiar o código da aplicação
COPY ./src /var/www/html/

EXPOSE 80
