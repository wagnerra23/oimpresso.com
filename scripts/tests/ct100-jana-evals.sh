#!/usr/bin/env bash
# ct100-jana-evals.sh — INVOCADOR dos 2 evals de staging da Jana (US-COPI-140).
#
# POR QUE ESTE SCRIPT EXISTE
# --------------------------
# Os 2 evals (jana:recall-eval --mode=real · jana:ragas-real-eval) estão declarados em
# app/Console/Kernel.php com ->environments(['staging']) — e NUNCA dispararam sozinhos.
# Medido 2026-07-17 (US-COPI-140):
#
#   - `schedule:run` = 0 ocorrências em TODO cron do CT 100 (4 entradas no crontab do
#     host, nenhuma é scheduler);
#   - container oimpresso-staging: sem cron, sem supervisord, /etc/periodic/* vazios.
#
# O gate de ambiente casa (APP_ENV do container É staging) — o que falta é o INVOCADOR.
# O container foi construído pra SERVIR HTTP (entrypoint Octane); ninguém cabeou
# scheduler nele. Resultado: os números do baseline honesto vieram todos de run MANUAL,
# e a órfã governance/ragas-real-trend congelou em 1 semana (2026-06-28) desde 04/07 —
# enquanto o transporte (ct100-ragas-publish.sh, dom 08:30) roda religiosamente e
# republica o mesmo report velho toda semana.
#
# CONTRASTE que prova o diagnóstico: o irmão jana:drift-sentinel é ->environments(['live'])
# e RODA semanal em prod, no horário (log copiloto-ai: 2026-07-05 06:01:29 e
# 2026-07-12 06:01:27, mock_mode:false). Mesmo módulo, mesma cadência — só muda o
# ambiente. Logo o defeito não é o Kernel, nem o comando, nem o gate: é o invocador.
# (Por isso o drift-sentinel NÃO está neste script: ele já roda.)
#
# CAMINHO A (decisão Wagner 2026-07-17, confirmada após errata): cron DIRETO por job,
# que é o padrão vivo do CT 100 — as 4 entradas do crontab do host invocam scripts
# direto, NENHUMA usa `schedule:run`. Invoca só o que está aqui: blast-radius zero.
#
# ⛔ POR QUE NÃO `schedule:run` (caminho B, recusado). Medido pelo RUNTIME — não por
# regex (3 tentativas de parsear erraram; ver _errata_2026_07_17 no baseline):
#
#   APP_ENV=staging · 82 eventos registrados · 77 filtrados · 5 RODARIAM:
#     jana:recall-eval --mode=real        <- queremos
#     jana:ragas-real-eval --json         <- queremos
#     errors:archive-stale-groups         <- colateral
#     kb:drift-detector --business-id=1   <- colateral (toca biz=1)
#     connector:health --notify           <- colateral, NOTIFICA
#
#   Reproduzir: iterar app(Schedule::class)->events() filtrando por
#   $e->runsInEnvironment(app()->environment()) dentro do container.
#   (⚠️ NÃO deduza isso lendo Kernel.php: o app registra 82 eventos e MÓDULOS
#   registram os seus — Kernel.php tem só 65 statements. A autoridade é o runtime.)
#
# CUSTO ACEITO DO CAMINHO A: a agenda passa a viver em DOIS lugares (o Kernel declara,
# este cron invoca). O Kernel segue como documentação da intenção; a verdade operacional
# é este arquivo. Os comentários dos 2 blocos no Kernel apontam pra cá.
#
# Instalado em: /opt/oimpresso-ragas/ct100-jana-evals.sh — cópia sincronizada
# MECANICAMENTE pelo self-update.sh do MCP (a cada 15min; mesma defesa anti-drift do
# fullsuite/ragas-publish — a pegadinha dos 13 dias do RUNBOOK-ct100-fullsuite.md).
# Único passo manual (1×, host CT 100 — idempotente):
#   ( crontab -l 2>/dev/null | grep -v ct100-jana-evals; \
#     echo '0 6 * * 0 flock -n /tmp/jana-evals.lock /opt/oimpresso-ragas/ct100-jana-evals.sh >> /opt/oimpresso-ragas/evals.log 2>&1' ) | crontab -
#
# Cron root: 0 6 * * 0 (host TZ America/Sao_Paulo => 06:00 BRT domingo). Os 2 rodam
# SEQUENCIALMENTE aqui — a serialização por construção substitui o espaçamento
# 06:30/07:00 que o Kernel usava pra evitar disputa de DB. Termina bem antes do
# transporte (ct100-ragas-publish.sh, dom 08:30) ler o report.
#
# HONESTIDADE (Tier 0): NÃO mascaramos falha. Cada eval roda com `set +e` isolado, o
# exit é registrado no log, e o script sai != 0 se QUALQUER um falhar — o cron guarda
# em evals.log. Um eval que faz SKIP honesto (sem OPENAI_API_KEY/contexto) sai 0 por
# design do comando e NÃO é tratado como erro aqui: o SKIP já vira semana inválida no
# trend (conta contra o ragas_real_uptime). Nunca inventamos run.
#
# @see memory/requisitos/Jana/SPEC.md#US-COPI-140
# @see memory/decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md
# @see scripts/tests/ct100-ragas-publish.sh (o transporte que lê o report que isto gera)
set -uo pipefail
# JANA_EVALS_TEST_BIN: seam do selftest — prependa um dir com `docker` mock. Vazio em
# produção (o PATH é hardcoded por higiene de cron). Mesmo padrão do irmão
# ct100-sdd-scorecard-snapshot.sh.
export PATH="${JANA_EVALS_TEST_BIN:+$JANA_EVALS_TEST_BIN:}/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

CONTAINER="${JANA_EVALS_CONTAINER:-oimpresso-staging}"
# Pisos NÃO vêm por flag (US-COPI-136): o dono único é thresholds_regressao em
# governance/jana-ragas-real-baseline.json, lido pelo comando em runtime. Passar
# --threshold-* aqui recriaria a régua paralela que a US-136 acabou de matar.
SAMPLE="${JANA_EVALS_SAMPLE:-0}" # 0 = gold-set completo; >0 = smoke barato

ts() { date '+%Y-%m-%d %H:%M:%S%z'; }
log() { echo "[$(ts)] $*"; }

docker inspect "$CONTAINER" >/dev/null 2>&1 || {
  log "FATAL: container $CONTAINER não existe — nada invocado (gap honesto no trend)"
  exit 1
}

SAMPLE_ARG=()
[ "$SAMPLE" -gt 0 ] 2>/dev/null && SAMPLE_ARG=(--sample-size="$SAMPLE")

rc_total=0

run_eval() {
  local nome="$1"; shift
  log "--- $nome: início"
  set +e
  docker exec -e DB_CONNECTION=mysql "$CONTAINER" php artisan "$@"
  local rc=$?
  set -e
  if [ $rc -eq 0 ]; then
    log "--- $nome: OK (exit 0)"
  else
    log "--- $nome: FALHOU (exit $rc) — ver saída acima. NÃO mascarado."
    rc_total=1
  fi
  return 0
}

log "=== ct100-jana-evals (US-COPI-140) · container=$CONTAINER · sample=$SAMPLE ==="

# Ordem = a mesma intenção do Kernel (06:30 → 07:00), agora serializada.
# jana:drift-sentinel NÃO entra aqui de propósito: é ->environments(['live']) e já roda
# em prod pelo scheduler do hPanel (log copiloto-ai 05/07 e 12/07 06:01). Invocá-lo
# daqui rodaria o canary DUAS vezes por semana, uma delas contra o corpus errado.
run_eval "recall-eval" jana:recall-eval --mode=real
run_eval "ragas-real-eval" jana:ragas-real-eval --json "${SAMPLE_ARG[@]}"

if [ $rc_total -eq 0 ]; then
  log "=== todos os evals invocados sem erro ==="
else
  log "=== ALGUM eval falhou — exit 1 (o cron registra em evals.log) ==="
fi
exit $rc_total
