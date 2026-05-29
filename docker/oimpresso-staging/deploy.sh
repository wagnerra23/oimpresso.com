#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# Deploy/atualiza staging.oimpresso.com no CT 100. Idempotente. Roda no HOST.
#
# Uso:  sh docker/oimpresso-staging/deploy.sh [branch]   (default: main)
#
# NÃO roda migration nem importa banco (são etapas à parte, com gate LGPD):
#   - F1 (casca):   php artisan migrate --seed   (banco vazio/seed)
#   - F3 (dados):   importar dump ANONIMIZADO    (ver anonimizar-staging)
#
# 2026-05-29 — correções (o deploy reportava exit 0 MASCARANDO falha de build):
#   (1) NÃO mascara erro: cada passo crítico aborta com tail do log. Antes,
#       `cmd | tail` deixava composer/build:inertia falharem INVISÍVEIS porque o
#       exit do pipe é o do `tail` (0) → staging servia bundle velho calado.
#   (2) Composer via imagem `composer:2` (a imagem mcp NÃO tem composer).
#   (3) Assets Inertia via shim `php`: o host do CT não tem php, e o
#       Vite/Wayfinder roda `php artisan wayfinder:generate` em build-time.
#       O shim faz `php` cair no container mcp (com os mounts do container vivo).
#       O bundle servido é protegido: backup → restore se o build falhar.
#   Ref: memory/requisitos/Infra/RUNBOOK-staging-ct100.md (Pegadinhas #1, #2).
# ─────────────────────────────────────────────────────────────────────────────
set -eu

BRANCH="${1:-main}"
CODE=/opt/oimpresso-staging/code
COMPOSE="$CODE/docker/oimpresso-staging/docker-compose.yml"
LOG=/tmp/oimpresso-staging-deploy.log
: > "$LOG"

# step <descrição> <cmd...> — roda, loga, e ABORTA com tail do log se falhar.
# (sem `cmd | tail`, que mascarava o exit code e deixava a falha passar.)
step() {
    _desc="$1"; shift
    echo "    \$ $*"
    if "$@" >>"$LOG" 2>&1; then
        return 0
    fi
    _rc=$?
    echo "!! FALHOU: $_desc (exit $_rc)"
    echo "---- últimas 30 linhas de $LOG ----"
    tail -30 "$LOG"
    exit 1
}

echo "==> 1/5  Código (branch: $BRANCH)"
if [ ! -d "$CODE/.git" ]; then
    echo "    primeiro deploy: clone local de /opt/oimpresso-mcp/code (rápido, hardlinks)"
    git clone /opt/oimpresso-mcp/code "$CODE"
    git -C "$CODE" remote set-url origin https://github.com/wagnerra23/oimpresso.com.git
fi
git -C "$CODE" fetch origin "$BRANCH"
git -C "$CODE" checkout -B "$BRANCH" "origin/$BRANCH"
git -C "$CODE" reset --hard "origin/$BRANCH"
echo "    HEAD: $(git -C "$CODE" rev-parse --short HEAD)"

echo "==> 2/5  Composer (imagem composer:2 — a imagem mcp NÃO tem composer)"
step "composer install" docker run --rm -v "$CODE:/app" -w /app composer:2 \
    install --no-interaction --optimize-autoloader --ignore-platform-reqs --no-scripts

echo "==> 3/5  Assets (Node $(node -v) no host; php via shim no container mcp)"
cd "$CODE"
step "npm ci" npm ci --no-audit --no-fund

# O Vite/Wayfinder chama `php artisan` em build-time, mas o host do CT não tem
# php. Shim temporário: faz `php` cair dentro do container mcp com os mounts do
# container vivo (storage + bootstrap/cache, senão o boot quebra em "cache path").
SHIM="$(mktemp -d)"
cat > "$SHIM/php" <<'PHP_SHIM'
#!/bin/sh
exec docker run --rm \
  -v /opt/oimpresso-staging/code:/var/www/html \
  -v /opt/oimpresso-staging/storage:/var/www/html/storage \
  -v /opt/oimpresso-staging/bootstrap-cache:/var/www/html/bootstrap/cache \
  -w /var/www/html --entrypoint php oimpresso/mcp:latest "$@"
PHP_SHIM
chmod +x "$SHIM/php"

# Protege o bundle servido: o Vite esvazia o outDir no início; se o build falhar
# no meio, restaura o bundle anterior pra staging não ficar sem assets.
BAK="$(mktemp -d)"
[ -d public/build-inertia ] && cp -r public/build-inertia "$BAK/build-inertia"

if PATH="$SHIM:$PATH" npm run build:inertia >>"$LOG" 2>&1 \
   && PATH="$SHIM:$PATH" npm run build >>"$LOG" 2>&1; then
    echo "    build OK"
    rm -rf "$SHIM" "$BAK"
else
    _rc=$?
    echo "!! FALHOU: build de assets (exit $_rc)"
    echo "---- últimas 40 linhas de $LOG ----"
    tail -40 "$LOG"
    if [ -d "$BAK/build-inertia" ]; then
        rm -rf public/build-inertia
        mv "$BAK/build-inertia" public/build-inertia
        echo "    bundle anterior restaurado (staging intacto)"
    fi
    rm -rf "$SHIM" "$BAK"
    exit 1
fi
rm -rf node_modules/.vite 2>/dev/null || true   # economiza disco

echo "==> 4/5  .env presente?"
if [ ! -f "$CODE/.env" ]; then
    echo "    !! FALTA $CODE/.env — copie de produção + aplique docker/oimpresso-staging/.env.staging.example"
    echo "    Abortando antes de subir container sem .env."
    exit 1
fi

echo "==> 5/5  Sobe container"
docker compose -f "$COMPOSE" up -d
echo ""
echo "OK. HEAD $(git -C "$CODE" rev-parse --short HEAD). Smoke:  curl -I https://staging.oimpresso.com/login"
echo "Logs:       docker logs -f oimpresso-staging"
