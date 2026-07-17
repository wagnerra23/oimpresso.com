#!/usr/bin/env bash
# staging-freshness-sentinel.sh — heartbeat de FRESCOR do checkout de STAGING (CT 100).
#
# POR QUE EXISTE: o clone do MCP tem `self-update.sh` (/15min) + a sentinela externa
# `mcp-drift-sentinel.yml`; o checkout de STAGING (`/opt/oimpresso-staging/code`, bind
# no container `oimpresso-staging`) NÃO tinha equivalente — então apodrecia em silêncio
# (HEAD ficava dias atrás de main) e CONVIDAVA hand-edit direto no servidor (drift Tier 0,
# `memory/proibicoes.md` §Ambiente). Incidente 2026-07-17: staging estava ~4 dias stale
# com edições não-commitadas na mão; só foi visto por acaso.
#
# ⚠️ NÃO-DESTRUTIVA POR DESIGN — o OPOSTO do self-update.sh. Staging é SCRATCHPAD de
# teste: as proibições mandam rodar Pest/PHPStan LÁ (container `oimpresso-staging`), então
# o checkout PRECISA ficar gravável e naturalmente carrega trabalho EM VOO. Um `reset
# --hard` / `pull` cego aqui apagaria o teste que alguém está rodando agora. Esta sentinela
# só MEDE e ALERTA — quem sincroniza é humano/sessão, com fetch + ff-only (ou descartar
# edições conscientemente), nunca esta script.
#
# POR QUE HOST (e não check in-app): só o HOST enxerga ao mesmo tempo o `.git/HEAD` do
# checkout de staging E o main-SHA fresco que o self-update do MCP já grava. O container
# MCP não monta o disco do staging; o endpoint HTTP do staging (/api/mcp/version) está
# quebrado (500) — logo o caminho robusto é filesystem, no host. E a sentinela vive FORA
# do checkout que vigia (senão apodrece junto com ele).
#
# Instalação (uma vez, no host CT 100 — copiar do repo pra path estável fora do checkout):
#   cp docker/oimpresso-staging/staging-freshness-sentinel.sh /opt/oimpresso-staging/staging-freshness-sentinel.sh
#   chmod +x /opt/oimpresso-staging/staging-freshness-sentinel.sh
#   crontab -e  →  adicionar:
#   0 * * * * flock -n /tmp/staging-freshness.lock /opt/oimpresso-staging/staging-freshness-sentinel.sh >> /opt/oimpresso-staging/freshness.log 2>&1
#
# Uso manual:   bash docker/oimpresso-staging/staging-freshness-sentinel.sh
# Auto-teste:   bash docker/oimpresso-staging/staging-freshness-sentinel.sh --selftest
#
# Exit codes:   0 = fresco / tolerado / não-aplicável · 2 = STALE (apodreceu) · 3 = indeterminado
set -uo pipefail

STAGING_DIR="${STAGING_CODE_DIR:-/opt/oimpresso-staging/code}"
# main-SHA fresco escrito pelo self-update.sh do MCP a cada 15min (fonte primária).
MAIN_SHA_FILE="${MAIN_SHA_FILE:-/opt/oimpresso-mcp/storage/app/deploy-latest-main-sha.txt}"
STATUS_FILE="${STATUS_FILE:-/opt/oimpresso-staging/freshness-status.json}"
THRESHOLD_DAYS="${STAGING_FRESHNESS_THRESHOLD_DAYS:-3}"
# Branch que o checkout DEVE seguir. O confronto SHA-vs-main só faz sentido nela; em
# qualquer outra (worktree/feature) o veredito é "não-aplicável" (staleness vs main ≠
# SHA-equality quando a branch tem commits próprios). Hoje staging roda em `main`.
TRACK_BRANCH="${STAGING_TRACK_BRANCH:-main}"

log() { echo "[$(date -Is)] [staging-freshness] $*"; }

# --- núcleo puro: veredito a partir de valores INJETADOS (sem git, sem relógio) ---
# args: head_sha  main_sha  head_age_days  threshold_days
# ecoa: fresco | atras-recente:<n>d | stale:<n>d | indeterminado:<motivo>
avaliar_frescor() {
  local head="$1" main="$2" age="$3" thr="$4"
  [ -z "$head" ] && { echo "indeterminado:sem-head"; return; }
  [ -z "$main" ] && { echo "indeterminado:sem-main"; return; }
  # compara tolerando short vs full (prefixo comum, mínimo 7 — igual DeployDriftChecker::mesmoSha)
  local n=$(( ${#head} < ${#main} ? ${#head} : ${#main} ))
  if [ "$n" -ge 7 ] && [ "${head:0:$n}" = "${main:0:$n}" ]; then
    echo "fresco"; return
  fi
  if [ "$age" -gt "$thr" ]; then echo "stale:${age}d"; else echo "atras-recente:${age}d"; fi
}

# --- selftest: controle-negativo que PROVA que a sentinela morde (repo §fixture boa/ruim) ---
if [ "${1:-}" = "--selftest" ]; then
  fail=0
  check() { if [ "$1" = "$2" ]; then echo "  ok: $3"; else echo "  FALHOU: $3 (esperava '$2', veio '$1')"; fail=1; fi; }
  check "$(avaliar_frescor abc1234 abc1234 0 3)"        "fresco"            "mesmo SHA = fresco"
  check "$(avaliar_frescor aaed49e1 aaed49e1560f 0 3)"  "fresco"            "short vs full mesmo prefixo = fresco"
  check "$(avaliar_frescor aaaa111 bbbb222 10 3)"       "stale:10d"         "SHA != + velho > thr = stale (MORDE)"
  check "$(avaliar_frescor aaaa111 bbbb222 1 3)"        "atras-recente:1d"  "SHA != + recente <= thr = tolerado"
  check "$(avaliar_frescor '' bbbb222 0 3)"             "indeterminado:sem-head" "sem head = indeterminado"
  check "$(avaliar_frescor aaaa111 '' 0 3)"             "indeterminado:sem-main" "sem main = indeterminado"
  if [ "$fail" = 0 ]; then echo "SELFTEST OK"; exit 0; else echo "SELFTEST FALHOU"; exit 1; fi
fi

# --- coleta real ---
head_sha="$(git -C "$STAGING_DIR" rev-parse HEAD 2>/dev/null || true)"
branch="$(git -C "$STAGING_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"

main_sha="$(tr -d '[:space:]' < "$MAIN_SHA_FILE" 2>/dev/null || true)"
if [ -z "$main_sha" ]; then
  # fallback read-only (NÃO fetch, NÃO escreve ref): pergunta o SHA de main direto ao remoto
  main_sha="$(git -C "$STAGING_DIR" ls-remote origin main 2>/dev/null | awk 'NR==1{print $1}')"
fi

head_ts="$(git -C "$STAGING_DIR" show -s --format=%ct HEAD 2>/dev/null || echo 0)"
now_ts="$(date +%s)"
age_days=0
[ "${head_ts:-0}" -gt 0 ] && age_days=$(( (now_ts - head_ts) / 86400 ))

# só vigia quando o checkout está na branch que deveria seguir (outra = staleness N/A)
if [ "$branch" != "$TRACK_BRANCH" ]; then
  veredito="nao-aplicavel:branch=${branch:-desconhecido}"
else
  veredito="$(avaliar_frescor "$head_sha" "$main_sha" "$age_days" "$THRESHOLD_DAYS")"
fi

# status file (machine-readable — discoverable por quem quiser plugar num painel/alerta)
mkdir -p "$(dirname "$STATUS_FILE")" 2>/dev/null || true
printf '{"veredito":"%s","head":"%s","main":"%s","branch":"%s","age_days":%s,"threshold_days":%s,"checked_at":"%s"}\n' \
  "$veredito" "$head_sha" "$main_sha" "$branch" "$age_days" "$THRESHOLD_DAYS" "$(date -Is)" > "$STATUS_FILE" 2>/dev/null || true

case "$veredito" in
  fresco|nao-aplicavel:*)
    log "OK ($veredito) — head=${head_sha:0:12} main=${main_sha:0:12}"
    exit 0 ;;
  atras-recente:*)
    log "INFO staging $veredito atrás de main (head=${head_sha:0:12} main=${main_sha:0:12}) — tolerado (< ${THRESHOLD_DAYS}d)"
    exit 0 ;;
  stale:*)
    log "ALERTA staging APODRECEU ($veredito) — head=${head_sha:0:12} != main=${main_sha:0:12} há > ${THRESHOLD_DAYS}d. Sincronizar com fetch + merge --ff-only (ou descartar edições conscientemente e reset) — NUNCA pull cego com trabalho em voo."
    exit 2 ;;
  *)
    log "ALERTA indeterminado ($veredito) — não medi frescor (head='${head_sha}' main='${main_sha}'). Checar $STAGING_DIR e $MAIN_SHA_FILE."
    exit 3 ;;
esac
