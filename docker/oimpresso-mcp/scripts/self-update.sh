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

# Sync da cópia do nightly full-suite (SDD FV-F3/P07): o cron do fullsuite roda
# /opt/oimpresso-fullsuite/ct100-fullsuite.sh, uma CÓPIA do versionado. O passo
# manual "atualizar a cópia após merge" (RUNBOOK-ct100-fullsuite.md) falhou 13 dias
# (18/jun→01/jul): P07 coverage nunca chegou no cron — 0 clover.xml em 4 nightlies
# com pcov JÁ na imagem. Sync mecânico aqui (roda a cada 15min): cmp evita touch
# sem mudança; mv atômico troca o inode — run em andamento segue lendo o fd antigo,
# nunca corrompe. Roda ANTES do early-exit de heartbeat de propósito: cópia driftada
# com checkout já em dia (o caso de hoje) também se cura.
FULLSUITE_SRC="$REPO_DIR/scripts/tests/ct100-fullsuite.sh"
FULLSUITE_DST="${FULLSUITE_SCRIPT:-/opt/oimpresso-fullsuite/ct100-fullsuite.sh}"
if [ -f "$FULLSUITE_SRC" ] && [ -d "$(dirname "$FULLSUITE_DST")" ]; then
  if ! cmp -s "$FULLSUITE_SRC" "$FULLSUITE_DST"; then
    install -m 0755 "$FULLSUITE_SRC" "$FULLSUITE_DST.tmp" && mv -f "$FULLSUITE_DST.tmp" "$FULLSUITE_DST" \
      && log "fullsuite: cópia do nightly sincronizada com o canônico" \
      || log "WARN: sync da cópia do fullsuite falhou (não-fatal)"
  fi
fi

# Mesmo sync anti-drift pra cópia do publish semanal do trend RAGAS real (ADR 0318 ·
# transporte pattern nightly-floor ADR 0279): o cron dom 08:30 BRT roda
# /opt/oimpresso-ragas/ct100-ragas-publish.sh. mkdir -p deliberado (dir novo — a cópia
# nasce no 1º sync sem passo manual; só a linha de crontab é manual 1×, ver header do .sh).
RAGASPUB_SRC="$REPO_DIR/scripts/tests/ct100-ragas-publish.sh"
RAGASPUB_DST="${RAGASPUB_SCRIPT:-/opt/oimpresso-ragas/ct100-ragas-publish.sh}"
if [ -f "$RAGASPUB_SRC" ]; then
  mkdir -p "$(dirname "$RAGASPUB_DST")"
  if ! cmp -s "$RAGASPUB_SRC" "$RAGASPUB_DST"; then
    install -m 0755 "$RAGASPUB_SRC" "$RAGASPUB_DST.tmp" && mv -f "$RAGASPUB_DST.tmp" "$RAGASPUB_DST" \
      && log "ragas-publish: cópia do semanal sincronizada com o canônico" \
      || log "WARN: sync da cópia do ragas-publish falhou (não-fatal)"
  fi
fi

git fetch --quiet origin main || { log "FATAL: git fetch falhou"; exit 1; }
LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/main)"

# SHA que o CONTAINER está rodando (gravado após cada recreate OK). O trigger do recreate é
# "container != origin/main", NÃO "checkout atrás de origin" — porque o systemd
# oimpresso-git-sync já dá `git pull` e adianta o HEAD, então comparar checkout vs origin fazia
# o force-recreate NUNCA disparar (container servia código velho · incidentes 2026-06-17 e -19).
STATE="${MCP_DEPLOYED_SHA:-$BK/deployed-sha.txt}"
DEPLOYED="$(cat "$STATE" 2>/dev/null || echo none)"

if [ "$LOCAL" = "$REMOTE" ] && [ "$REMOTE" = "$DEPLOYED" ]; then
  log "já em origin/main (${REMOTE:0:9}) e container em dia — heartbeat ok"
  exit 0
fi

log "deploy: checkout ${LOCAL:0:9} · container ${DEPLOYED:0:9} -> origin/main ${REMOTE:0:9}"
TS="$(date +%Y%m%d-%H%M%S)"
printf 'OLD_HEAD=%s\nNEW_HEAD=%s\nDEPLOYED=%s\nWHEN=%s\n' "$LOCAL" "$REMOTE" "$DEPLOYED" "$TS" > "$BK/rollback-$TS.txt"

# Sincroniza o checkout só se atrás (o git-sync pode já ter puxado). Backup dos dirty antes do reset.
if [ "$LOCAL" != "$REMOTE" ]; then
  git status --porcelain | sed 's/^...//' > "$BK/dirty-$TS.list"
  if [ -s "$BK/dirty-$TS.list" ]; then
    tar czf "$BK/dirty-$TS.tar.gz" -T "$BK/dirty-$TS.list" 2>/dev/null \
      && log "backup dos dirty files em $BK/dirty-$TS.tar.gz" \
      || log "WARN: backup parcial dos dirty files (não-fatal)"
  fi
  git reset --hard origin/main || { log "FATAL: reset --hard falhou"; exit 1; }
fi

# Diff base = o SHA que o container roda (DEPLOYED), não o HEAD local. Fallback p/ LOCAL se sem estado.
DIFFBASE="$DEPLOYED"; [ "$DIFFBASE" = "none" ] && DIFFBASE="$LOCAL"

# composer install só se composer.lock/json mudou (host não tem composer → via container).
if ! git diff --quiet "$DIFFBASE" "$REMOTE" -- composer.lock composer.json; then
  log "composer.lock/json mudou — composer install via container composer:2"
  docker run --rm -v "$REPO_DIR":/var/www/html -w /var/www/html composer:2 \
    install --no-interaction --optimize-autoloader --ignore-platform-reqs 2>&1 | tail -5 \
    || log "WARN: composer install falhou (segue — recreate ainda aplica código)"
fi

# rebuild da imagem só se docker/oimpresso-mcp mudou (Dockerfile/compose/entrypoint).
if ! git diff --quiet "$DIFFBASE" "$REMOTE" -- docker/oimpresso-mcp/; then
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
  echo "$REMOTE" > "$STATE"
  log "OK: deploy ${REMOTE:0:9} saudável ($HEALTH_URL) — SHA gravado em $STATE"
  exit 0
fi
log "ALARME: smoke falhou após deploy ${REMOTE:0:9} — ver 'docker logs oimpresso-mcp'."
log "        rollback: cd $REPO_DIR && git reset --hard $LOCAL && docker compose -f $COMPOSE_FILE up -d --force-recreate"
exit 1
