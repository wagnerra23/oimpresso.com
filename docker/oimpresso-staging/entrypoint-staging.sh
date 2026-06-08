#!/bin/sh
# Entrypoint STAGING — FrankenPHP modo CLÁSSICO (sem Octane workers).
#
# Cada request boota o framework fresh (igual PHP-FPM do Hostinger) → evita
# state-leak do UltimatePOS, que NÃO é Octane-safe (singletons/static entre requests).
# Ver docker/oimpresso-staging/docker-compose.yml + ADR 0235.

set -e
cd /var/www/html

# Remove Telescope do package discovery (mesmo motivo do MCP: tabela ausente no DB
# faz cada artisan call disparar dezenas de INSERTs falhando).
if [ -f bootstrap/cache/packages.php ]; then
    php -r "\$f='bootstrap/cache/packages.php'; \$p=require \$f; unset(\$p['laravel/telescope']); file_put_contents(\$f,'<?php return '.var_export(\$p,true).';');" 2>/dev/null \
        && echo "[staging] Telescope removido do package discovery" \
        || echo "[staging] Telescope removal skip (não-fatal)"
fi

# Storage + cache writable
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Staging = dinâmico (facilita debug). Limpa caches stale; NÃO faz config:cache
# agressivo (cada artisan call com timeout pra nunca travar o boot do container).
echo "[staging] Limpando caches..."
timeout 30 php artisan config:clear 2>&1 | tail -1 || echo "[skip config:clear]"
timeout 30 php artisan route:clear  2>&1 | tail -1 || echo "[skip route:clear]"
rm -rf storage/framework/views/*.php 2>/dev/null || true

# Symlink storage público (idempotente) pra assets/uploads aparecerem
[ -L public/storage ] || timeout 15 php artisan storage:link 2>&1 | tail -1 || true

echo "[staging] Boot OK. FrankenPHP modo CLÁSSICO (sem workers) na :80..."
# php-server usa o directive Caddy `php_server` → front-controller Laravel
# (try_files {path} index.php) embutido. Cada request = processo PHP isolado.
exec frankenphp php-server --listen :80 --root /var/www/html/public
