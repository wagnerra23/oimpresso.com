#!/bin/sh
# MEM-MCP-1.c-perf (ADR 0053) — Entrypoint do container MCP
#
# Warm-up Laravel ANTES de aceitar requests:
#   1. Limpa caches stale (volume montado pode ter cache de host antigo)
#   2. config:cache    — gera bootstrap/cache/config.php (~80% boot speedup)
#   3. event:cache     — gera bootstrap/cache/events.php (subscribers cacheados)
#   4. view:cache      — pré-compila Blade views
#   5. NÃO route:cache — UltimatePOS tem 2 rotas com nome `business.update`
#                        (Modules/Superadmin + main app). Resolver implicaria
#                        tocar core. Aceito tradeoff: rotas re-parseadas a cada
#                        request (~50ms a mais é tolerável vs risco de corrupção).
#
# Após warm-up, supervisord assume e mantém php-fpm + nginx vivos.

set -e

cd /var/www/html

echo "[entrypoint] Limpando caches stale..."
php artisan config:clear || true
php artisan event:clear || true
php artisan view:clear || true

# Garante que diretórios storage estão writable (volume mount pode vir vazio)
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         storage/app/public \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint] Compilando caches (config + event + view)..."
php artisan config:cache 2>&1 | tail -3 || echo "[entrypoint] config:cache falhou (não-fatal)"
php artisan event:cache 2>&1 | tail -3 || echo "[entrypoint] event:cache falhou (não-fatal)"
php artisan view:cache 2>&1 | tail -3 || echo "[entrypoint] view:cache falhou (não-fatal)"

# Remove Telescope dos pacotes auto-discovered se MCP_DISABLE_TELESCOPE=true.
# Telescope insere boot overhead E falha (telescope_entries não existe no
# Hostinger MySQL — apenas lá lógica do Wagner em dev).
if [ "${MCP_DISABLE_TELESCOPE:-true}" = "true" ] && [ -f bootstrap/cache/packages.php ]; then
    echo "[entrypoint] Removendo Telescope do package discovery..."
    php -r "
        \$file = 'bootstrap/cache/packages.php';
        \$pkgs = require \$file;
        \$count_before = count(\$pkgs);
        unset(\$pkgs['laravel/telescope']);
        \$count_after = count(\$pkgs);
        file_put_contents(\$file, '<?php return ' . var_export(\$pkgs, true) . ';');
        echo \"[entrypoint] packages.php: {\$count_before} → {\$count_after} pacotes\n\";
    " || echo "[entrypoint] Telescope removal falhou (não-fatal)"
fi

echo "[entrypoint] Warm-up completo. Iniciando supervisord..."
exec "$@"
