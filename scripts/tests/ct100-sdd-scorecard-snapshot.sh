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
# FONTE DO SCORECARD = ARTEFATO VERSIONADO governance/sdd-scorecard.json (SSOT,
# ADR 0279 Opção A), medido/publicado pelo CI sdd-scorecard-publish.yml (diário
# 07:10 BRT + push(main)), que tem o ambiente COMPLETO (GH_TOKEN, materialização
# da branch órfã governance/nightly-floor). NÃO re-medir aqui no host: o CT 100
# não tem esses transportes e a re-medição diverge — em 2026-07-12 deu composta
# 64,1 (k=6, 7/13 vivas) vs 41,0 (k=7) do artefato versionado, número fantasma
# não-reproduzível (pego pelo adversário da grade de réguas 2026-07-12).
# UMA medição (CI) → UM artefato (repo) → snapshot só JOIN com baseline + persist.
#
# MECÂNICA:
#   1. Copia $CODE/governance/sdd-scorecard.json (checkout sincronizado pelo
#      self-update a cada 15min ⇒ lag máx ~1 dia do publish diário; push(main)
#      re-publica antes disso) pra storage/app (bind-mount rw do container).
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
# HONESTIDADE (Tier 0): se o artefato estiver ausente/inválido ou o comando falhar,
# o script sai != 0 e o dia fica SEM row nova (o brief mostra o snapshot de ontem) —
# a falha aparece no cron.log e no ->onFailure() do comando. NUNCA inventa row.
#
# STALENESS (advisory): se o último commit do artefato tiver mais que SDD_MAX_AGE_DAYS
# (default 3), loga WARNING alto e SEGUE. Escolha deliberada: abortar deixaria o dia
# sem row (pior sinal — o brief mostraria ontem sem explicação); row honesta + WARNING
# no snapshot.log preserva o sintoma de diagnóstico do RUNBOOK (publish parado).
#
# Selftest (CI governance-script-tests.yml + local): scripts/tests/ct100-sdd-scorecard-snapshot.test.sh
set -euo pipefail
# SDD_TEST_BIN: seam do selftest — prependa um dir com `docker` mock. Vazio em produção.
export PATH="${SDD_TEST_BIN:+$SDD_TEST_BIN:}/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

CONTAINER="${SDD_CONTAINER:-oimpresso-mcp}"
# checkout versionado do MCP (self-update.sh + oimpresso-git-sync.timer o mantêm em
# origin/main — mais fresco que o clone do fullsuite, que só sincroniza na nightly).
CODE="${SDD_CODE:-/opt/oimpresso-mcp/code}"
SRC="${SDD_SRC:-$CODE/governance/sdd-scorecard.json}"
# storage é bind-mount /opt/oimpresso-mcp/storage -> /var/www/html/storage (rw).
# Escrevo o JSON aqui (fora do checkout git => imune ao `git reset --hard` do self-update).
INPUT_HOST="${SDD_INPUT_HOST:-/opt/oimpresso-mcp/storage/app/sdd-scorecard-input.json}"
INPUT_CT="${SDD_INPUT_CT:-/var/www/html/storage/app/sdd-scorecard-input.json}"

log() { echo "[$(date -Is)] [sdd-snapshot] $*"; }

[ -s "$SRC" ] || { log "FATAL: $SRC ausente/vazio (checkout $CODE desatualizado? publish nunca rodou?)"; exit 1; }
grep -q '"metrics"' "$SRC" || { log "FATAL: $SRC sem chave metrics (artefato corrompido?)"; exit 1; }

# Proveniência no log: sha do checkout + último commit que tocou o artefato (o corpo
# do JSON é determinístico por design — sem timestamp; a rastreabilidade é o git).
cd "$CODE"
log "fonte: artefato versionado governance/sdd-scorecard.json @ checkout $(git rev-parse --short HEAD 2>/dev/null || echo '?') (último commit do artefato: $(git log -1 --format='%h %cs' -- governance/sdd-scorecard.json 2>/dev/null || echo '?'))"

# GUARD DE STALENESS (advisory, não aborta): sem isso o snapshot carimba row FRESCA
# com artefato potencialmente VELHO e mata o sintoma de diagnóstico do RUNBOOK
# (publish parado ⇒ brief continua "atualizado"). O corpo do JSON é determinístico
# (sem timestamp, by design) — o relógio é o git do checkout. Decisão conservadora:
# row honesta COM aviso > dia sem row (abortar esconderia o snapshot de ontem também);
# o WARNING no snapshot.log é o sintoma que o RUNBOOK usa.
MAX_AGE_DAYS="${SDD_MAX_AGE_DAYS:-3}"
ARTIFACT_CT="$(git log -1 --format=%ct -- governance/sdd-scorecard.json 2>/dev/null || true)"
if [ -n "$ARTIFACT_CT" ]; then
  AGE_DAYS=$(( ($(date +%s) - ARTIFACT_CT) / 86400 ))
  if [ "$AGE_DAYS" -gt "$MAX_AGE_DAYS" ]; then
    log "WARNING: artefato governance/sdd-scorecard.json está ${AGE_DAYS}d sem commit (> ${MAX_AGE_DAYS}d) — publish diário parado? (ver sdd-scorecard-publish.yml + self-update do checkout). Row do dia SEGUE, mas o número pode estar velho."
  fi
else
  log "aviso: idade do artefato indeterminada (git log vazio — checkout raso/sem histórico?)"
fi

cp "$SRC" "$INPUT_HOST"
[ -s "$INPUT_HOST" ] || { log "FATAL: cópia do scorecard vazia"; exit 1; }

log "persistindo snapshot no container $CONTAINER (Hostinger prod DB)"
docker exec "$CONTAINER" php artisan governance:sdd-scorecard-snapshot --input="$INPUT_CT"

log "done"
