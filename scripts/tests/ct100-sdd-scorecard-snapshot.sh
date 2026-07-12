#!/usr/bin/env bash
# ct100-sdd-scorecard-snapshot.sh — SDD P06 / GT-G7 (ADR 0275 §1). Snapshot DIÁRIO
# do scorecard SDD em mcp_sdd_scorecard_history (composta v1 + alertas), pra a linha
# SDD do brief parar de ficar stale (refrescada à mão até 2026-07-01).
#
# POR QUE NO CT 100 (não Hostinger): decisão Wagner 2026-07-01 — o `schedule:run`
# NÃO roda no Hostinger (shared hosting) e IA/governança não vive lá (ADR 0062).
# O Kernel.php agenda o comando às 07:10 BRT com ->environments(['live']), mas esse
# gate só dispara se algum schedule:run rodar num env `live` — não é o caso. Este
# wrapper roda o comando DIRETO (sem schedule:run), então o gate de env não se aplica.
#
# MECÂNICA (o container oimpresso-mcp NÃO tem node; o host tem node v20):
#   1. node (HOST, checkout fresco /opt/oimpresso-mcp/code) gera o scorecard JSON.
#      O output vai pra storage/app (bind-mount do container) pra ser visível dentro.
#   2. `php artisan governance:sdd-scorecard-snapshot --input=<json>` roda DENTRO do
#      container oimpresso-mcp — que conecta na Hostinger prod DB, onde o brief LÊ a
#      linha SDD. O flag --input é o caminho canônico "sem node" (ADR 0275 §3), o
#      mesmo que testes/CI usam. O comando é idempotente por dia (delete+insert).
#
# Instalado em: /opt/oimpresso-governance/ct100-sdd-scorecard-snapshot.sh — cópia
# sincronizada MECANICAMENTE pelo self-update.sh do MCP (a cada 15min; mesma defesa
# anti-drift do fullsuite/ragas — pegadinha dos 13 dias no RUNBOOK-ct100-fullsuite.md).
# Único passo manual (1×, host CT 100 — idempotente):
#   ( crontab -l 2>/dev/null | grep -v ct100-sdd-scorecard-snapshot; \
#     echo '10 7 * * * flock -n /tmp/sdd-scorecard-snapshot.lock /opt/oimpresso-governance/ct100-sdd-scorecard-snapshot.sh >> /opt/oimpresso-governance/snapshot.log 2>&1' ) | crontab -
# Cron root: 10 7 * * * (host TZ America/Sao_Paulo => 07:10 BRT — mesmo horário
# canônico do Kernel.php, 10min após governance:scorecard-snapshot 07:00).
#
# HONESTIDADE (Tier 0): se o node ou o comando falharem, o script sai != 0 e o dia
# fica SEM row nova (o brief mostra o snapshot de ontem) — a falha aparece no cron.log
# e no ->onFailure() do comando. NUNCA inventa row.
set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

CONTAINER="${SDD_CONTAINER:-oimpresso-mcp}"
# checkout versionado do MCP (self-update.sh + oimpresso-git-sync.timer o mantêm em
# origin/main — mais fresco que o clone do fullsuite, que só sincroniza na nightly).
CODE="${SDD_CODE:-/opt/oimpresso-mcp/code}"
MJS="$CODE/scripts/governance/sdd-scorecard.mjs"
# storage é bind-mount /opt/oimpresso-mcp/storage -> /var/www/html/storage (rw).
# Escrevo o JSON aqui (fora do checkout git => imune ao `git reset --hard` do self-update).
INPUT_HOST="${SDD_INPUT_HOST:-/opt/oimpresso-mcp/storage/app/sdd-scorecard-input.json}"
INPUT_CT="${SDD_INPUT_CT:-/var/www/html/storage/app/sdd-scorecard-input.json}"

log() { echo "[$(date -Is)] [sdd-snapshot] $*"; }

[ -f "$MJS" ] || { log "FATAL: $MJS ausente (checkout $CODE desatualizado?)"; exit 1; }
command -v node >/dev/null 2>&1 || { log "FATAL: node ausente no host"; exit 1; }

# O mjs re-invoca node com paths RELATIVOS ao cwd (spawn interno) → precisa rodar DENTRO
# do checkout, o mesmo cwd que o comando usa via base_path(). Sem `cd`: node aborta com
# "Cannot find module '/root/scripts/...'" (cwd do cron = /root).
cd "$CODE"
log "gerando scorecard JSON (node $(node --version), checkout $(git rev-parse --short HEAD 2>/dev/null || echo '?'))"
node scripts/governance/sdd-scorecard.mjs --json > "$INPUT_HOST"
[ -s "$INPUT_HOST" ] || { log "FATAL: scorecard JSON vazio"; exit 1; }

log "persistindo snapshot no container $CONTAINER (Hostinger prod DB)"
docker exec "$CONTAINER" php artisan governance:sdd-scorecard-snapshot --input="$INPUT_CT"

log "done"
