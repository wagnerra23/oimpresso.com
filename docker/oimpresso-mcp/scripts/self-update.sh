#!/usr/bin/env bash
# self-update.sh — GitOps pull-deploy do MCP server no CT 100 (ADR 0062 + 0256).
#
# POR QUE EXISTE: o deploy.yml é Hostinger-only (ADR 0062 separa os runtimes), então
# NÃO havia caminho main→CT100-MCP. Resultado: incidente 2026-06-17 — o container
# rodou ~17 dias de código velho (imagem 29/mai, checkout 31/mai) sem ninguém notar,
# porque os DADOS (DB compartilhada) ficam frescos e mascaram o drift do CÓDIGO.
#
# COMO: o host CLONA o repo por HTTPS (origin já configurado), então o caminho mais
# simples e SEM secret novo é o host se auto-atualizar (GitOps pull) por cron. Pareado
# com a sentinela EXTERNA mcp-drift-sentinel.yml (roda no GitHub, fora do tailnet) que
# grita se este script parar de funcionar — pro drift nunca mais ser silencioso.
#
# 2 pegadinhas que o README antigo não cobria (descobertas no incidente):
#   1. main teve história reescrita → `git pull` (merge) quebra com "no common
#      ancestor". Use `fetch + reset --hard origin/main` (igual deploy.yml faz).
#   2. opcache.validate_timestamps=Off no container → código novo no bind-mount NÃO
#      sobe sem `up -d --force-recreate`. Um `git pull` sem recreate é no-op silencioso.
#
# Instalação (uma vez, no host CT 100 — única config de host permitida):
#   */15 * * * * flock -n /tmp/mcp-self-update.lock /opt/oimpresso-mcp/code/docker/oimpresso-mcp/scripts/self-update.sh >> /opt/oimpresso-mcp/logs/self-update.log 2>&1
#
# Uso manual:  bash docker/oimpresso-mcp/scripts/self-update.sh
set -uo pipefail

REPO_DIR="${MCP_CODE_DIR:-/opt/oimpresso-mcp/code}"
COMPOSE_FILE="docker/oimpresso-mcp/docker-compose.yml"
HEALTH_URL="${MCP_HEALTH_URL:-https://mcp.oimpresso.com/api/mcp/health}"
BK="${MCP_BACKUP_DIR:-/opt/oimpresso-mcp/backups}"
LOCK="${MCP_LOCK:-/tmp/mcp-self-update.lock}"

log() { echo "[$(date -Is)] [self-update] $*"; }

# Single-instance: evita a corrida de deploys concorrentes (dois recreates do mesmo
# container ~ao mesmo tempo geraram um core dump no incidente 2026-06-17).
exec 9>"$LOCK" || { log "não consegui abrir lock $LOCK"; exit 1; }
flock -n 9 || { log "outra execução em andamento — saindo"; exit 0; }

cd "$REPO_DIR" || { log "FATAL: $REPO_DIR não existe"; exit 1; }
mkdir -p "$BK"

git fetch --quiet origin main || { log "FATAL: git fetch falhou"; exit 1; }
LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/main)"

if [ "$LOCAL" = "$REMOTE" ]; then
  log "já em origin/main (${REMOTE:0:9}) — nada a fazer (heartbeat ok)"
  exit 0
fi

log "drift detectado: ${LOCAL:0:9} -> ${REMOTE:0:9} — atualizando"
TS="$(date +%Y%m%d-%H%M%S)"
printf 'OLD_HEAD=%s\nNEW_HEAD=%s\nWHEN=%s\n' "$LOCAL" "$REMOTE" "$TS" > "$BK/rollback-$TS.txt"

# Backup dos dirty files ANTES do reset --hard (clobber). Memory/ + artefatos manuais.
git status --porcelain | sed 's/^...//' > "$BK/dirty-$TS.list"
if [ -s "$BK/dirty-$TS.list" ]; then
  tar czf "$BK/dirty-$TS.tar.gz" -T "$BK/dirty-$TS.list" 2>/dev/null \
    && log "backup dos dirty files em $BK/dirty-$TS.tar.gz" \
    || log "WARN: backup parcial dos dirty files (não-fatal)"
fi

git reset --hard origin/main || { log "FATAL: reset --hard falhou"; exit 1; }

# composer install só se composer.lock/json mudou (host não tem composer → via container).
if ! git diff --quiet "$LOCAL" "$REMOTE" -- composer.lock composer.json; then
  log "composer.lock/json mudou — composer install via container composer:2"
  docker run --rm -v "$REPO_DIR":/var/www/html -w /var/www/html composer:2 \
    install --no-interaction --optimize-autoloader --ignore-platform-reqs 2>&1 | tail -5 \
    || log "WARN: composer install falhou (segue — recreate ainda aplica código)"
fi

# rebuild da imagem só se docker/oimpresso-mcp mudou (Dockerfile/compose/entrypoint).
if ! git diff --quiet "$LOCAL" "$REMOTE" -- docker/oimpresso-mcp/; then
  log "docker/oimpresso-mcp mudou — rebuild da imagem"
  docker compose -f "$COMPOSE_FILE" build 2>&1 | tail -5 \
    || log "WARN: build falhou (segue com imagem atual; recreate aplica código novo)"
fi

# SEMPRE force-recreate: opcache.validate_timestamps=Off → sem recreate o código não sobe.
log "recreate (busta opcache frozen)"
docker compose -f "$COMPOSE_FILE" up -d --force-recreate 2>&1 | tail -5

# Smoke: caminho completo Traefik→container, espera o JSON status:ok.
ok=""
for i in $(seq 1 15); do
  sleep 4
  if curl -fsS --max-time 6 "$HEALTH_URL" 2>/dev/null | grep -q '"status":"ok"'; then ok=1; break; fi
done
if [ -n "$ok" ]; then
  log "OK: deploy ${REMOTE:0:9} saudável ($HEALTH_URL)"
  exit 0
fi
log "ALARME: smoke falhou após deploy ${REMOTE:0:9} — ver 'docker logs oimpresso-mcp'."
log "        rollback: cd $REPO_DIR && git reset --hard $LOCAL && docker compose -f $COMPOSE_FILE up -d --force-recreate"
exit 1
