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
# Escalação do STALE pra mcp_alertas (brief/inbox do time) via `docker exec` no container
# do MCP — o único caminho, já que o staging tem DB isolada e o exit-2 sozinho só vive
# no log do host. Best-effort: se docker/container/DB estiverem fora, o exit 2 + log
# seguem sendo o sinal. `0` desliga (dev/host sem docker).
MCP_CONTAINER="${MCP_CONTAINER:-oimpresso-mcp}"
ESCALATE="${STAGING_FRESHNESS_ESCALATE:-1}"
# Idade máxima tolerada do MAIN_SHA_FILE antes de ele ser tratado como PODRE e descartado.
# Derivada da cadência do PRODUTOR (systemd `oimpresso-git-sync.timer`, /5min) — 72x a
# cadência: generoso o bastante pra absorver atraso do timer, apertado pra pegar morte
# real. NÃO troque por um número desligado dessa cadência (§5 2026-08-27: janela de
# tolerância maior que a taxa de mudança do objeto = verde por construção).
MAIN_SHA_MAX_AGE_S="${STAGING_MAIN_SHA_MAX_AGE_S:-21600}"   # 6h

log() { echo "[$(date -Is)] [staging-freshness] $*"; }

# Escala o veredito STALE pra mcp_alertas via o comando canônico (PersistsDriftAlert:
# idempotente/dia + escalação). NUNCA derruba o sentinela — é aditivo ao exit 2.
escalar_mcp_alertas() {
  [ "$ESCALATE" = "1" ] || { log "escalação p/ mcp_alertas desligada (STAGING_FRESHNESS_ESCALATE=0)"; return 0; }
  command -v docker >/dev/null 2>&1 || { log "docker ausente — só log (sem mcp_alertas)"; return 0; }
  docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$MCP_CONTAINER" \
    || { log "container $MCP_CONTAINER não está up — só log (sem mcp_alertas)"; return 0; }
  if docker exec "$MCP_CONTAINER" php artisan governance:staging-freshness-alert \
        --verdict="$1" --head="$2" --main="$3" --age="$4" >/dev/null 2>&1; then
    log "escalado p/ mcp_alertas (brief/inbox) via $MCP_CONTAINER"
  else
    log "AVISO: docker exec p/ mcp_alertas falhou (best-effort) — exit 2 + este log seguem sendo o sinal"
  fi
}

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

# --- núcleo puro: a referência de main vinda do arquivo ainda é CONFIÁVEL? ---
# args: idade_do_arquivo_em_segundos (vazio = arquivo ausente)  tolerancia_s
# ecoa: usar | descartar:<motivo>
#
# POR QUE EXISTE (medido 2026-09-21, e é o defeito que esta função fecha): o
# MAIN_SHA_FILE estava congelado em 0404b631aa39 (2026-08-13 21:23Z) — 1698 commits
# atrás do tip — porque o produtor (`oimpresso-git-sync.timer`) morreu em 2026-08-14
# com `Could not resolve host: github.com` e ninguém viu por 38 dias. `systemctl
# is-active` dizia `active`: isso é DECLARAÇÃO; os campos de runtime
# `NextElapseUSecRealtime` e `LastTriggerUSec` estavam ambos VAZIOS (§5 2026-07-17 —
# medir pela consequência, não pela declaração).
# O consumidor só caía no fallback quando o arquivo estava VAZIO; STALE ele engolia
# como verdade. Efeito: o veredito `fresco` ficou INALCANÇÁVEL por 39 dias (o HEAD do
# staging nunca ia bater com um main de agosto) e a sentinela degradou EM SILÊNCIO de
# "frescor vs main" para "idade do commit do HEAD" — ainda pegava staging-parado, mas
# cegou staging-em-commit-recente-porém-muito-atrás.
# É a §5 2026-08-01: o instrumento que falha nem sempre devolve VAZIO — às vezes devolve
# um valor PLAUSÍVEL, e o vazio (único caso que o código tratava) é o caso fácil.
referencia_confiavel() {
  local idade="$1" tol="$2"
  [ -z "$idade" ] && { echo "descartar:arquivo-ausente"; return; }
  case "$idade" in ''|*[!0-9-]*) echo "descartar:idade-ilegivel"; return ;; esac
  if [ "$idade" -gt "$tol" ]; then echo "descartar:stale:${idade}s>${tol}s"; else echo "usar"; fi
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
  # referencia_confiavel — o eixo que faltava (regressão medida em 2026-09-21)
  check "$(referencia_confiavel 60 21600)"      "usar"                          "arquivo de 1min = usar"
  check "$(referencia_confiavel 21600 21600)"   "usar"                          "exatamente no limite = usar (nao-estrito)"
  check "$(referencia_confiavel 21601 21600)"   "descartar:stale:21601s>21600s" "1s alem do limite = descartar (MORDE na borda)"
  check "$(referencia_confiavel 3369600 21600)" "descartar:stale:3369600s>21600s" "arquivo de 39d = descartar (o caso real medido)"
  check "$(referencia_confiavel '' 21600)"      "descartar:arquivo-ausente"     "arquivo ausente = descartar"
  check "$(referencia_confiavel abc 21600)"     "descartar:idade-ilegivel"      "idade ilegivel = descartar (nunca 'usar' por acidente)"
  # CONTROLE NEGATIVO ponta-a-ponta: com a referencia PODRE o veredito 'fresco' e
  # inalcancavel para o MESMO head; com a referencia VIVA ele volta a ser alcancavel.
  check "$(avaliar_frescor e57b78bf5 0404b631aa39 0 3)" "atras-recente:0d" "referencia PODRE: 'fresco' inalcancavel (o bug)"
  check "$(avaliar_frescor e57b78bf5 e57b78bf54e7 0 3)" "fresco"           "referencia VIVA: 'fresco' alcancavel (o fix)"
  if [ "$fail" = 0 ]; then echo "SELFTEST OK"; exit 0; else echo "SELFTEST FALHOU"; exit 1; fi
fi

# --- coleta real ---
head_sha="$(git -C "$STAGING_DIR" rev-parse HEAD 2>/dev/null || true)"
branch="$(git -C "$STAGING_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"

# A referência de main tem DUAS portas: o arquivo que o produtor grava (/5min) e o
# remoto. O arquivo só vale enquanto for FRESCO — ver `referencia_confiavel` acima.
main_sha=""
main_src="nenhuma"
arquivo_idade_s=""
if [ -f "$MAIN_SHA_FILE" ]; then
  arquivo_mtime="$(stat -c %Y "$MAIN_SHA_FILE" 2>/dev/null || true)"
  [ -n "$arquivo_mtime" ] && arquivo_idade_s=$(( $(date +%s) - arquivo_mtime ))
fi
confianca="$(referencia_confiavel "$arquivo_idade_s" "$MAIN_SHA_MAX_AGE_S")"
if [ "$confianca" = "usar" ]; then
  main_sha="$(tr -d '[:space:]' < "$MAIN_SHA_FILE" 2>/dev/null || true)"
  if [ -n "$main_sha" ]; then main_src="arquivo"; else confianca="descartar:arquivo-vazio"; fi
fi
if [ -z "$main_sha" ]; then
  log "referência do arquivo: $confianca — caindo no remoto (read-only)"
  # fallback read-only (NÃO fetch, NÃO escreve ref): pergunta o SHA de main direto ao remoto
  main_sha="$(git -C "$STAGING_DIR" ls-remote origin main 2>/dev/null | awk 'NR==1{print $1}')"
  [ -n "$main_sha" ] && main_src="ls-remote"
fi
# Se as DUAS portas falharem, main_sha fica vazio e avaliar_frescor devolve
# indeterminado:sem-main => exit 3. NUNCA colapsar "não consegui medir" num veredito
# de saúde (§5 2026-07-29) nem numa acusação (LC-33).

head_ts="$(git -C "$STAGING_DIR" show -s --format=%ct HEAD 2>/dev/null || echo 0)"
now_ts="$(date +%s)"
age_days=0
[ "${head_ts:-0}" -gt 0 ] && age_days=$(( (now_ts - head_ts) / 86400 ))

# Branch VAZIA e branch DIFERENTE não são a mesma coisa, e confundi-las é fail-open:
# "estou numa worktree/feature" é N/A legítimo (exit 0); "não consegui ler branch
# nenhuma" (STAGING_DIR sumiu, bind quebrou, não é repo git) é NÃO-MEDIÇÃO e tem de
# sair por indeterminado/exit 3. Antes de 2026-09-21 as duas caíam em
# `nao-aplicavel:branch=desconhecido` + exit 0 — um diretório inexistente devolvia
# SAÚDE (LC-33 / §5 2026-07-29). Achado ao enumerar os demais ramos deste mesmo
# instrumento enquanto se consertava o ramo da referência (§5 2026-09-03).
if [ -z "$branch" ]; then
  veredito="indeterminado:sem-branch"
elif [ "$branch" != "$TRACK_BRANCH" ]; then
  veredito="nao-aplicavel:branch=$branch"
else
  veredito="$(avaliar_frescor "$head_sha" "$main_sha" "$age_days" "$THRESHOLD_DAYS")"
fi

# status file (machine-readable — discoverable por quem quiser plugar num painel/alerta).
# `main_src` + `main_ref` são o que torna um `fresco` AUDITÁVEL: sem eles não dá pra
# distinguir "bateu com o main vivo" de "bateu com um arquivo podre de agosto".
mkdir -p "$(dirname "$STATUS_FILE")" 2>/dev/null || true
printf '{"veredito":"%s","head":"%s","main":"%s","main_src":"%s","main_ref":"%s","branch":"%s","age_days":%s,"threshold_days":%s,"checked_at":"%s"}\n' \
  "$veredito" "$head_sha" "$main_sha" "$main_src" "$confianca" "$branch" "$age_days" "$THRESHOLD_DAYS" "$(date -Is)" > "$STATUS_FILE" 2>/dev/null || true

case "$veredito" in
  fresco|nao-aplicavel:*)
    log "OK ($veredito) — head=${head_sha:0:12} main=${main_sha:0:12}"
    exit 0 ;;
  atras-recente:*)
    log "INFO staging $veredito atrás de main (head=${head_sha:0:12} main=${main_sha:0:12}) — tolerado (< ${THRESHOLD_DAYS}d)"
    exit 0 ;;
  stale:*)
    log "ALERTA staging APODRECEU ($veredito) — head=${head_sha:0:12} != main=${main_sha:0:12} há > ${THRESHOLD_DAYS}d. Sincronizar com fetch + merge --ff-only (ou descartar edições conscientemente e reset) — NUNCA pull cego com trabalho em voo."
    escalar_mcp_alertas "$veredito" "$head_sha" "$main_sha" "$age_days"
    exit 2 ;;
  *)
    log "ALERTA indeterminado ($veredito) — não medi frescor (head='${head_sha}' main='${main_sha}'). Checar $STAGING_DIR e $MAIN_SHA_FILE."
    exit 3 ;;
esac
