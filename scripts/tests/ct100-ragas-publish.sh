#!/usr/bin/env bash
# ct100-ragas-publish.sh — write-side do transporte do RAGAS real (ADR 0318 +
# pattern nightly-floor ADR 0279 Opção A). Roda no HOST do CT 100 (Proxmox),
# DEPOIS do cron semanal `jana:ragas-real-eval` (dom 07:00 BRT, container
# oimpresso-staging via Kernel.php): extrai o report da run, faz merge no trend
# acumulado (ragas-trend-compute.mjs) e publica governance/ragas-real-trend.json
# na branch ÓRFÃ governance/ragas-real-trend (mesma deploy key do floor — órfã =
# imune a shallow + NÃO toca a proteção do main). O read-side (sdd-scorecard.mjs
# measureRagasRealUptime) materializa esse arquivo no CI e mede ragas_real_uptime.
#
# Instalado em: /opt/oimpresso-ragas/ct100-ragas-publish.sh — cópia sincronizada
# MECANICAMENTE pelo self-update.sh do MCP (a cada 15min; mesma defesa anti-drift
# do fullsuite — pegadinha dos 13 dias catalogada no RUNBOOK-ct100-fullsuite.md).
# Único passo manual (1×, host CT 100 — idempotente):
#   ( crontab -l 2>/dev/null | grep -v ct100-ragas-publish; \
#     echo '30 8 * * 0 flock -n /tmp/ragas-publish.lock /opt/oimpresso-ragas/ct100-ragas-publish.sh >> /opt/oimpresso-ragas/publish.log 2>&1' ) | crontab -
# Cron root: 30 8 * * 0 (host TZ America/Sao_Paulo => 08:30 BRT domingo — 1h30
# depois do eval; eval de 51 perguntas termina em minutos).
#
# HONESTIDADE (Tier 0): se o eval fez SKIP (sem OPENAI_API_KEY/contexto), o
# report skipped É publicado — vira semana INVÁLIDA no trend (conta contra o
# uptime). Se NADA rodou (sem report e sem log), sai 1 SEM publicar — a semana
# ausente aparece como gap no trend (read-side conta gap = inválida). Nunca
# inventa entrada.
set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

CONTAINER="${RAGAS_CONTAINER:-oimpresso-staging}"
KEY="${RAGAS_DEPLOY_KEY:-/root/.ssh/oimpresso_floor_deploy}"
REPO="${RAGAS_REPO:-git@github.com:wagnerra23/oimpresso.com.git}"
BRANCH="governance/ragas-real-trend"
# compute versionado: reusa o clone do MCP (self-update.sh sincroniza a cada 15min —
# mais fresco que o clone do fullsuite, que só sincroniza na nightly 02:00)
CODE="${RAGAS_CODE:-/opt/oimpresso-mcp/code}"
COMPUTE="$CODE/scripts/tests/ragas-trend-compute.mjs"
GIT_SSH="ssh -i $KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

[ -f "$KEY" ] || { echo "FATAL: deploy key ausente ($KEY) — sem transporte"; exit 1; }
[ -f "$COMPUTE" ] || { echo "FATAL: $COMPUTE ausente (clone $CODE desatualizado?)"; exit 1; }

WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT

# --- [1/4] report da última run -------------------------------------------------
# Contrato preferido: o comando persiste storage/app/governance/ragas-real-eval-latest.json.
# Fallback (código do container anterior ao contrato): último bloco JSON pretty-printed
# do log agendado (appendOutputTo storage/logs/ragas-real-eval.log).
if docker exec "$CONTAINER" test -f storage/app/governance/ragas-real-eval-latest.json 2>/dev/null; then
  docker exec "$CONTAINER" cat storage/app/governance/ragas-real-eval-latest.json > "$WORK/report.json"
  echo "[report] via storage/app/governance/ragas-real-eval-latest.json"
elif docker exec "$CONTAINER" test -f storage/logs/ragas-real-eval.log 2>/dev/null; then
  docker exec "$CONTAINER" cat storage/logs/ragas-real-eval.log \
    | awk '/^\{/{buf=""; inb=1} inb{buf=buf $0 "\n"} /^\}/{if(inb){last=buf; inb=0}} END{printf "%s", last}' \
    > "$WORK/report.json"
  echo "[report] via último bloco JSON de storage/logs/ragas-real-eval.log (fallback)"
else
  echo "FATAL: nem report nem log no container — jana:ragas-real-eval nunca rodou? Semana fica como GAP (inválida). Nada publicado."
  exit 1
fi
node -e "JSON.parse(require('fs').readFileSync('$WORK/report.json','utf8'))" \
  || { echo "FATAL: report extraído não é JSON válido — nada publicado (gap honesto)"; exit 1; }

# --- [2/4] trend existente da órfã (se houver) -----------------------------------
# TRÊS estados, não dois. A forma anterior (`git clone ... 2>/dev/null` dentro de um
# `if`) colapsava "não consegui falar com o remoto" em "a órfã não existe" — e seguir
# por aí recomputa o trend DO ZERO, que o `push -f` do passo [4/4] então grava por
# cima da série inteira. INCIDENTE 2026-08-30 (publish.log): o clone falhou, o compute
# caiu de "7 semana(s)" para "1 semana(s)", e só o push ter falhado JUNTO salvou os 7
# pontos. Não é hipótese — está nas duas linhas seguidas do log daquele domingo.
#
# O oráculo que distingue é `ls-remote`: rc≠0 = não sei (aborta); rc=0 + saída vazia =
# a branch de fato não existe (1ª publicação); rc=0 + saída = existe, e aí carregar
# deixa de ser "se houver" e passa a ser obrigatório.
EXISTING_ARG=()
if REMOTO_HEADS=$(git -c core.sshCommand="$GIT_SSH" ls-remote --heads "$REPO" "$BRANCH" 2>&1); then
  if [ -n "$REMOTO_HEADS" ]; then
    git clone -q --depth 1 --branch "$BRANCH" \
         -c core.sshCommand="$GIT_SSH" "$REPO" "$WORK/orfa" \
      || { echo "FATAL: a órfã $BRANCH EXISTE mas o clone falhou — abortando para não regravar a série com push -f"; exit 1; }
    [ -f "$WORK/orfa/governance/ragas-real-trend.json" ] \
      || { echo "FATAL: órfã $BRANCH clonada SEM governance/ragas-real-trend.json — estado inesperado, abortando"; exit 1; }
    EXISTING_ARG=(--existing "$WORK/orfa/governance/ragas-real-trend.json")
    echo "[trend] existente carregado da órfã"
  else
    echo "[trend] remoto respondeu e a branch $BRANCH não existe — 1ª publicação"
  fi
else
  echo "FATAL: não consegui consultar o remoto ($BRANCH) — abortando ANTES de recomputar do zero"
  printf '%s\n' "$REMOTO_HEADS" | tail -2
  exit 1
fi

# --- [3/4] merge idempotente (mesma semana = substitui) ---------------------------
mkdir -p "$WORK/out/governance"
node "$COMPUTE" --report "$WORK/report.json" "${EXISTING_ARG[@]}" \
  --out "$WORK/out/governance/ragas-real-trend.json"

# --- [4/4] publica na órfã (single commit force — histórico vive NO json) ---------
( cd "$WORK/out" \
  && git init -q \
  && git config core.sshCommand "$GIT_SSH" \
  && git add governance/ragas-real-trend.json \
  && git -c user.email=ct100-ragas@oimpresso.local -c user.name="ct100-ragas-trend" \
       commit -q -m "chore(governance): ragas real trend semanal [skip ci]" \
  && git push -f "$REPO" HEAD:refs/heads/"$BRANCH" 2>&1 | tail -2 ) \
  && echo "[trend] publicado em $BRANCH" \
  || { echo "[trend] push falhou — read-side segue com trend anterior (ou notYet)"; exit 1; }
