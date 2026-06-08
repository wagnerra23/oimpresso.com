#!/bin/sh
# MEM-MCP-1.c-perf Fase 1 (ADR 0053) — Entrypoint Octane + FrankenPHP
#
# Diferença do entrypoint FPM:
#   - FrankenPHP é single-process (sem supervisord)
#   - Octane mantém Laravel boot em memória (config:cache crítico pra primeiro boot)
#   - Workers reciclam após max-requests=500 (definido em CMD)
#
# Warm-up steps:
#   1. Remove Telescope de packages.php (idêntico ao entrypoint FPM)
#   2. config:cache + event:cache + view:cache
#   3. exec octane:start (substitui shell, vira PID 1)

set -e

cd /var/www/html

# ============================================================================
# CRÍTICO: Remove Telescope antes de qualquer artisan call.
# Telescope auto-discover registra EventWatcher que tenta INSERT em
# telescope_entries (tabela não existe no Hostinger MySQL) — cada artisan
# fica fazendo dezenas de roundtrips MySQL falhando.
# ============================================================================
if [ "${MCP_DISABLE_TELESCOPE:-true}" = "true" ] && [ -f bootstrap/cache/packages.php ]; then
    echo "[entrypoint-octane] Removendo Telescope do package discovery..."
    php -r "
        \$file = 'bootstrap/cache/packages.php';
        \$pkgs = require \$file;
        \$count_before = count(\$pkgs);
        unset(\$pkgs['laravel/telescope']);
        \$count_after = count(\$pkgs);
        file_put_contents(\$file, '<?php return ' . var_export(\$pkgs, true) . ';');
        echo \"[entrypoint-octane] packages.php: {\$count_before} → {\$count_after} (Telescope removido)\n\";
    " || echo "[entrypoint-octane] Telescope removal falhou (não-fatal)"
fi

# Garante storage writable
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         storage/app/public \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "[entrypoint-octane] Limpando caches stale..."
# CADA artisan call com timeout 30s — alguns boot path do Laravel/módulos hangam
# em queries SQL durante boot (visto em 29-abr, deps Spatie/Scout). Timeout
# garante que entrypoint nunca trava o container.
timeout 30 php artisan config:clear 2>&1 | tail -2 || echo "[skip config:clear]"
timeout 30 php artisan event:clear 2>&1 | tail -2 || echo "[skip event:clear]"
# view:clear pulado: o diretório storage/framework/views começa vazio, e
# view:clear historicamente trava em alguns boot paths Laravel + nWidart.
# Octane recompila views on-demand a cada request — sem prejuízo.
rm -rf /var/www/html/storage/framework/views/*.php 2>/dev/null || true

echo "[entrypoint-octane] Compilando caches..."
timeout 60 php artisan config:cache 2>&1 | tail -2 || echo "[skip config:cache]"
timeout 60 php artisan event:cache 2>&1 | tail -2 || echo "[skip event:cache]"
# view:cache também pulado — Octane handle on-demand. Pré-compilar dá GAIN
# pequeno mas ARRIESGA travar entrypoint inteiro se algum view/Blade falhar parse.

# Octane install — idempotente, publica config/octane.php se ainda não existe.
if [ ! -f config/octane.php ]; then
    echo "[entrypoint-octane] Publicando config/octane.php..."
    php artisan octane:install --server=frankenphp --no-interaction 2>&1 | tail -5 || true
fi

echo "[entrypoint-octane] Boot completo. Iniciando Octane (FrankenPHP)..."
exec "$@"
