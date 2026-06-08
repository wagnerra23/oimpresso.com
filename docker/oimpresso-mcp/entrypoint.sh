#!/bin/sh
# MEM-MCP-1.c-perf (ADR 0053) — Entrypoint do container MCP
#
# Warm-up Laravel ANTES de aceitar requests:
#   1. Limpa caches stale (volume montado pode ter cache de host antigo)
#   2. config:cache    — gera bootstrap/cache/config.php (~80% boot speedup)
#   3. event:cache     — gera bootstrap/cache/events.php (subscribers cacheados)
#   4. view:cache      — pré-compila Blade views
#   5. route:cache deve voltar a funcionar (PR #336 removeu colisão de
#                        `business.update` em Modules/Officeimpresso). Validar
#                        no próximo deploy CT 100; se OK, habilitar abaixo.
#
# Após warm-up, supervisord assume e mantém php-fpm + nginx vivos.

set -e

cd /var/www/html

# ============================================================================
# CRÍTICO: Remove Telescope de packages.php ANTES de qualquer `artisan`.
# ============================================================================
# Por que primeiro: Telescope auto-discover registra EventWatcher que escuta
# *todos* os eventos Laravel (incluindo modules.*.register do nWidart) e tenta
# INSERT INTO telescope_entries — tabela que NÃO existe no MySQL Hostinger.
# Cada artisan call fica fazendo dezenas de roundtrips MySQL falhando.
#
# Sintoma sem isso: `php artisan config:clear` trava 60s+.
# Com isso: <1s.
if [ "${MCP_DISABLE_TELESCOPE:-true}" = "true" ] && [ -f bootstrap/cache/packages.php ]; then
    echo "[entrypoint] Removendo Telescope do package discovery (antes de qualquer artisan)..."
    php -r "
        \$file = 'bootstrap/cache/packages.php';
        \$pkgs = require \$file;
        \$count_before = count(\$pkgs);
        unset(\$pkgs['laravel/telescope']);
        \$count_after = count(\$pkgs);
        file_put_contents(\$file, '<?php return ' . var_export(\$pkgs, true) . ';');
        echo \"[entrypoint] packages.php: {\$count_before} → {\$count_after} pacotes (Telescope removido)\n\";
    " || echo "[entrypoint] Telescope removal falhou (não-fatal)"
fi

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

echo "[entrypoint] Limpando caches stale (volume mount pode trazer cache de outro host)..."
php artisan config:clear 2>&1 | tail -2 || echo "[entrypoint] config:clear falhou (não-fatal)"
php artisan event:clear 2>&1 | tail -2 || echo "[entrypoint] event:clear falhou (não-fatal)"
php artisan view:clear 2>&1 | tail -2 || echo "[entrypoint] view:clear falhou (não-fatal)"

echo "[entrypoint] Compilando caches (config + event + view)..."
php artisan config:cache 2>&1 | tail -2 || echo "[entrypoint] config:cache falhou (não-fatal)"
php artisan event:cache 2>&1 | tail -2 || echo "[entrypoint] event:cache falhou (não-fatal)"
php artisan view:cache 2>&1 | tail -2 || echo "[entrypoint] view:cache falhou (não-fatal)"

# route:cache desabilitado historicamente por colisão de nome `business.update`.
# PR #336 (2026-05-09) removeu o `->name('business.update')` em Modules/Officeimpresso
# (era redundante — view usa action() direta). Habilitar route:cache aqui após
# validação local + smoke no CT 100. Por enquanto mantido off pra não arriscar.

echo "[entrypoint] Warm-up completo. Iniciando supervisord..."
exec "$@"
