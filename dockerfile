FROM php:8.0-fpm

# Instalar dependências do sistema para PHP e extensões necessárias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl

# Instalar extensões do PHP
RUN docker-php-ext-install pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar o código fonte do aplicativo para o container
WORKDIR /var/www
COPY . /var/www

# Instalar dependências do projeto via Composer
RUN composer install --no-interaction

# Expor a porta 8000
EXPOSE 8000

# Comando para iniciar o servidor do Laravel
CMD php artisan serve --host=0.0.0.0 --port=8000