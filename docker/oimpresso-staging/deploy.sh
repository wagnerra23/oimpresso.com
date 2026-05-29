#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# Deploy/atualiza staging.oimpresso.com no CT 100. Idempotente. Roda no HOST.
#
# Uso:  sh docker/oimpresso-staging/deploy.sh [branch]   (default: main)
#
# NÃO roda migration nem importa banco (são etapas à parte, com gate LGPD):
#   - F1 (casca):   php artisan migrate --seed   (banco vazio/seed)
#   - F3 (dados):   importar dump ANONIMIZADO    (ver anonimizar-staging)
# ─────────────────────────────────────────────────────────────────────────────
set -e

BRANCH="${1:-main}"
CODE=/opt/oimpresso-staging/code
IMG=oimpresso/mcp:latest
COMPOSE="$CODE/docker/oimpresso-staging/docker-compose.yml"

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

echo "==> 2/5  Composer (via imagem $IMG)"
docker run --rm -v "$CODE:/var/www/html" -w /var/www/html --entrypoint sh "$IMG" \
    -c "composer install --no-interaction --optimize-autoloader 2>&1 | tail -5"

echo "==> 3/5  Assets (Node $(node -v) no host)"
cd "$CODE"
npm ci --no-audit --no-fund 2>&1 | tail -3
npm run build:inertia 2>&1 | tail -3
npm run build 2>&1 | tail -3
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
echo "OK. Smoke:  curl -I https://staging.oimpresso.com/login"
echo "Logs:       docker logs -f oimpresso-staging"
