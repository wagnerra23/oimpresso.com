# Reverb daemon — Laravel WebSocket broadcaster
#
# Build: o context é a raiz do repo oimpresso (o repo é sincronizado pra
# /opt/oimpresso-app no CT). Compose.yml chama com:
#   build:
#     context: /opt/oimpresso-app
#     dockerfile: infra/proxmox/docker-host/reverb/Dockerfile
#
# Runtime: php artisan reverb:start --host=0.0.0.0 --port=8080
# Traefik routea via Host(reverb.oimpresso.com), TLS Let's Encrypt automático.

FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        pcntl \
        sockets \
        bcmath \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copia primeiro só composer.* pra cache de layer das deps
COPY composer.json composer.lock ./
COPY lib-custom/ ./lib-custom/

# composer install sem rodar scripts (precisaria do app completo)
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --ignore-platform-reqs \
    && rm -rf /root/.composer

# Copia o resto do app (.dockerignore filtra storage logs, node_modules, etc.)
COPY . /app

# Otimização autoload + ignora pacotes que não precisam de scripts em runtime
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi || true

# Storage e bootstrap/cache devem ser writable pelo www-data (UID 33 no debian, mas
# Alpine PHP-CLI roda como root por default; pra prod vale criar user dedicado).
RUN mkdir -p storage/framework/cache/data \
              storage/framework/sessions \
              storage/framework/views \
              storage/framework/testing \
              storage/logs \
              bootstrap/cache \
    && chmod -R 0775 storage bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD nc -z 127.0.0.1 8080 || exit 1

CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080", "--debug"]
