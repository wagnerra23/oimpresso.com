# MEM-MCP-1.b (ADR 0053) — Imagem do MCP server (PHP 8.4 FPM + nginx + supervisord)
#
# Stack: PHP 8.4 + Nginx 1.27 + supervisord (single container).
# Por que single container: simplicidade Dia 2 — split em multi-container
# (php-fpm + nginx separados) vira otimização Dia 7+ se necessário.
#
# Bind mount em runtime (no compose):
#   /var/www/html ← /opt/oimpresso-mcp/code (read-only)
#   /var/www/html/storage ← /opt/oimpresso-mcp/storage (rw)

FROM php:8.4-fpm-alpine

# Instala Nginx + supervisor + extensões PHP do Laravel
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        bash \
        git \
        zip \
        unzip \
        oniguruma-dev \
        libxml2-dev \
        libzip-dev \
        icu-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        bcmath \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurações
COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/zz-mcp-overrides.ini

# Diretórios necessários + permissões
RUN mkdir -p /var/log/nginx /run/nginx /var/log/supervisor /var/www/html \
    && chown -R www-data:www-data /var/www/html \
    && chmod 755 /var/www/html

WORKDIR /var/www/html

# Healthcheck — Traefik usa esse pra saber se está vivo
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/api/mcp/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
