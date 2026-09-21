#!/usr/bin/env bash
# ct100-paridade-lote.sh — mede a paridade protótipo × vivo (ADR 0408) NO CT 100,
# contra o staging, e publica o resultado na branch órfã `governance/paridade-lote`.
#
# ── POR QUE NO CT 100, e não no CI ────────────────────────────────────────────
# Decisão [W] 2026-09-21: "pode aumentar a capacidade do servidor, tem máquina para isso".
# Medido no host: **16 cores · 32 GB**, `node v20.20.2`, `node_modules` já presente no
# checkout do fullsuite, e `https://staging.oimpresso.com/login` respondendo 200. O runner
# do GitHub não tinha como competir: a conta é `User`, o repo é público e há **0**
# self-hosted runners — larger runners simplesmente não existem ali (medido).
#
# E a razão mais forte não é capacidade: **o staging tem `APP_URL` real**. Foi assim que a
# única medição bem-sucedida do lote aconteceu — o `RESUMO.md` versionado de 2026-09-18 diz
# `Base viva: https://staging.oimpresso.com`, com 18 IGUAL e 52 DIVERGE de verdade.
#
# ── A CAUSA RAIZ DAS 8 TENTATIVAS NO CI (registrada pra ninguém repetir) ──────
# O `design-smoke-ci.yml` escreve `APP_URL=http://localhost` (linha 174, SEM porta). O
# Laravel monta o redirect de `/_visreg-login/{id}?to=` a partir do `APP_URL`, então ele
# aponta pra `http://localhost:80` — onde nada escuta. O curl de diagnóstico devolveu
# `HTTP 302000` (`-L` concatenando `302` + `000`) e `exit 7` = "failed to connect".
#
# Ou seja: o auth-bridge respondia 302 CERTO; quem não conectava era o DESTINO. Nunca foi
# servidor, worker, concorrência nem FrankenPHP — e cada uma das 8 runs trocou uma peça do
# eixo errado (`artisan serve` cru · `--no-reload` + WORKERS=8 · servidor do Pest ·
# FrankenPHP standalone). O `DesignSmokeTest` sempre passou porque o Pest navega por rota
# RELATIVA e não depende do `APP_URL`.
#
# ── MECÂNICA ──────────────────────────────────────────────────────────────────
#   1. usa o checkout do fullsuite (já sincronizado, já com `node_modules`);
#   2. garante o chromium do Playwright (o host não tem cache — medido);
#   3. lê `VISREG_LOGIN_TOKEN` do `.env` do staging. A rota é env-guarded E exige segredo,
#      porque staging é público na internet: sem o token ela responde **404** (medido), e o
#      lote cairia em /login medindo a tela de login contra o protótipo;
#   4. roda o lote contra `https://staging.oimpresso.com`;
#   5. publica `RESUMO.md` + medidas na branch órfã, mesmo transporte do
#      `ct100-fullsuite.sh` → `governance/nightly-floor` (repo efêmero + push -f, sem PR,
#      sem tocar main).
#
# ── HONESTIDADE (Tier 0) ──────────────────────────────────────────────────────
# Sai != 0 e NÃO publica quando a medição não aconteceu. `ERRO` no RESUMO é falha de
# NAVEGAÇÃO, não divergência de design — e foi justamente ele que deixou 5 rodadas saírem
# `success` medindo nada. Divergência NUNCA falha: render pareado não bloqueia (ADR 0290
# segue recusada; a 0408 libera MEDIR, não BLOQUEAR).
#
# ── INSTALAÇÃO (host CT 100, idempotente) ─────────────────────────────────────
#   ( crontab -l 2>/dev/null | grep -v ct100-paridade-lote; \
#     echo '40 4 * * 1 flock -n /tmp/paridade-lote.lock /opt/oimpresso-governance/ct100-paridade-lote.sh >> /opt/oimpresso-governance/paridade-lote.log 2>&1' ) | crontab -
# Segunda 04:40 BRT — mesma cadência semanal que a ADR 0408 fixou, e o universo do lote
# (telas `anchored`) muda por TRABALHO humano, não por relógio.
#
# Selftest: scripts/tests/ct100-paridade-lote.test.sh
set -euo pipefail
# PLT_TEST_BIN: seam do selftest — prependa um dir com mocks de `node`/`git`/`curl`.
export PATH="${PLT_TEST_BIN:+$PLT_TEST_BIN:}/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

CODE="${PLT_CODE:-/opt/oimpresso-fullsuite/code}"
STAGING_ENV="${PLT_STAGING_ENV:-/opt/oimpresso-staging/code/.env}"
BASE_URL="${PLT_BASE_URL:-https://staging.oimpresso.com}"
BRANCH="${PLT_BRANCH:-governance/paridade-lote}"
DEPLOY_KEY="${PLT_DEPLOY_KEY:-/root/.ssh/oimpresso_floor_deploy}"
MEDIDAS="governance/design/targets/medidas"

log() { printf '[paridade-lote] %s\n' "$*"; }
fail() { printf '[paridade-lote] ERRO: %s\n' "$*" >&2; exit 1; }

[ -d "$CODE" ] || fail "checkout ausente: $CODE"
cd "$CODE"

# ── 1. token: sem ele a rota é 404 e o lote mede a tela de LOGIN ──────────────
[ -f "$STAGING_ENV" ] || fail "env do staging ausente: $STAGING_ENV"
TOKEN="$(sed -n 's/^VISREG_LOGIN_TOKEN=//p' "$STAGING_ENV" | head -1 | tr -d '"'"'"'[:space:]')"
[ -n "$TOKEN" ] || fail "VISREG_LOGIN_TOKEN vazio em $STAGING_ENV — a rota responde 404 e o lote mediria /login"
export VISREG_LOGIN_TOKEN="$TOKEN"

# ── 2. o alvo está de pé? (e o auth-bridge responde com o token?) ─────────────
HEALTH="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$BASE_URL/login" || echo 000)"
[ "$HEALTH" = "200" ] || fail "staging não respondeu em $BASE_URL/login (HTTP $HEALTH) — nada foi medido"
log "staging OK (HTTP $HEALTH)"

# CONTROLE SEQUENCIAL no caminho REAL, antes de medir. É o que faltou nas 8 runs do CI:
# `-w` imprime o(s) código(s) e `-L` segue o redirect, então um `302` seguido de `000`
# denuncia redirect pra host/porta que não conecta — que era exatamente o defeito lá.
ALVO="$BASE_URL/_visreg-login/1?t=$TOKEN&to=%2F"
SOLO="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 -L "$ALVO" || echo 000)"
case "$SOLO" in
  200|200200) log "auth-bridge OK (HTTP $SOLO)" ;;
  *) fail "auth-bridge não fecha sozinho (HTTP $SOLO). 302+000 = redirect pra host que não conecta (checar APP_URL); 404 = token inválido. NÃO é concorrência." ;;
esac

# ── 3. chromium (o host não tem cache — medido 2026-09-21) ───────────────────
[ -d node_modules ] || fail "node_modules ausente em $CODE — rode npm ci antes"
log "garantindo chromium do playwright"
npx --yes playwright install --with-deps chromium >/dev/null 2>&1 \
  || npx --yes playwright install chromium >/dev/null 2>&1 \
  || fail "playwright install falhou — sem browser não há medição"

# ── 4. a medição ──────────────────────────────────────────────────────────────
log "medindo contra $BASE_URL"
set +e
node scripts/design/design-diff-lote.mjs --base-url "$BASE_URL" 2>&1 | tee /tmp/paridade-lote.log
RC=${PIPESTATUS[0]}
set -e

[ -f "$MEDIDAS/RESUMO.md" ] || fail "o lote não gravou RESUMO.md — não houve medição (ausência de medida não é 'tudo igual')"

# ERRO = falha de NAVEGAÇÃO. Publicar isso como veredito de fidelidade foi o que mascarou
# 5 rodadas; o consumidor tem o mesmo assert, aqui em shell pra decidir se PUBLICA.
ERROS="$(grep -c '| ERRO |' "$MEDIDAS/RESUMO.md" || true)"
# `^| ` casa tambem o CABECALHO da tabela — o 1o run no CT 100 reportou 69 onde o
# consumidor dizia 68. Descontar 1 seria fragil; exclui-se a linha cujo 1o campo e "Tela".
TELAS="$(awk -F'|' '/^\| / && $2 !~ /^ *Tela *$/ {n++} END{print n+0}' "$MEDIDAS/RESUMO.md")"
if [ "${ERROS:-0}" -gt 0 ]; then
  fail "$ERROS de $TELAS telas falharam na NAVEGAÇÃO — falha de MEDIÇÃO, não divergência. Nada publicado. Ver /tmp/paridade-lote.log"
fi
log "medição OK: $TELAS linha(s), 0 ERRO (rc do lote: $RC)"

# resumo legível, mesmo consumidor do CI (denominador primeiro, corte por causa)
LOTE_OUTCOME=success node scripts/design/lote-resumo-ci.mjs --check | tee /tmp/paridade-lote-resumo.md

# ── 5. publicação: branch órfã, sem PR, sem tocar main ───────────────────────
if [ ! -f "$DEPLOY_KEY" ]; then
  log "AVISO: deploy key ausente ($DEPLOY_KEY) — medição feita, publicação PULADA"
  exit 0
fi
PUB="$(mktemp -d)"
mkdir -p "$PUB/$MEDIDAS"
cp -r "$MEDIDAS/." "$PUB/$MEDIDAS/"
cp /tmp/paridade-lote-resumo.md "$PUB/RESUMO-LEGIVEL.md" 2>/dev/null || true
cd "$PUB"
git init -q -b main
git add -A
git -c user.email=paridade-lote@oimpresso.local -c user.name="ct100-paridade-lote" \
  commit -q -m "chore(design): paridade prototipo x vivo — $TELAS tela(s), 0 ERRO [skip ci]"
GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" \
  git push -f git@github.com:wagnerra23/oimpresso.com.git "HEAD:refs/heads/$BRANCH" 2>&1 | tail -2
log "publicado em $BRANCH"
