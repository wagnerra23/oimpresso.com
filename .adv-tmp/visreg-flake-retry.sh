#!/usr/bin/env bash
# visreg-flake-retry.sh — retry-once guard pros steps ENFORCING do visual-regression.yml.
#
# POR QUE EXISTE
#   Os gates Financeiro (`financeiro-states`, `financeiro-flows`) viraram REQUIRED e
#   ENFORCING (#4239). Sem retry, um único flap de EXECUÇÃO trava o merge do time inteiro
#   — e a reação natural (arrancar o enforcing) jogaria fora o trabalho todo. O fluxo do
#   Financeiro levou 11 patches (#4223→#4235) pra estabilizar por relógio cross-processo;
#   o risco residual é flake de execução (browser não sobe, socket do Playwright cai,
#   timeout do React montar / "Alvo visual não ficou disponível"), NÃO diff de pixel.
#
# CONTRATO DE SEGURANÇA — POR QUE O DIFF REAL NUNCA É RE-RODADO (é o ponto todo)
#   O double-threshold (Tests\Browser\Support\VisregThreshold — o MOTOR, não tocar) já
#   separa "regressão clara > τ_alto" (2%) da zona cinza: uma regressão de pixel vira um
#   `test()->fail()` DETERMINÍSTICO com "REGRESSÃO CLARA" / "dimensões divergem" — que NÃO
#   contém nenhuma palavra de browser/timeout. Se a saída casa a assinatura de pixel-diff,
#   saímos com o código de falha ORIGINAL, SEM 2ª chance. Re-rodar aí mascararia justamente
#   a regressão que o gate existe pra pegar (controle negativo: um diff intencional > 2%
#   AINDA falha o gate).
#
# POLÍTICA (fail-closed, allowlist de flake + denylist dura de pixel-diff)
#   1. pixel-diff detectado  → NUNCA re-roda (mesmo que também haja palavra de flake).
#   2. flake de execução conhecido E sem pixel-diff → re-roda UMA vez.
#   3. falha desconhecida (ex.: assertSee do fluxo quebrou) → NÃO re-roda (conservador:
#      não mascarar falha real que não identificamos como flake).
#
# USO: scripts/tests/visreg-flake-retry.sh <args do pest>
#   ex.: scripts/tests/visreg-flake-retry.sh tests/Browser/CoreScreens/FinanceiroFlowBaselineTest.php
set -uo pipefail

LOG="${VISREG_RETRY_LOG:-/tmp/visreg-flake-retry.log}"

# Assinatura de REGRESSÃO DE PIXEL (VisregThreshold::assertBandedScreenshot). Denylist dura:
# se qualquer uma bater, é veredito real do double-threshold → sem retry.
PIXEL_DIFF_RE='REGRESSÃO CLARA|τ_alto|dimensões divergem|layout-shift|VisregThreshold \['

# Assinatura de FLAKE DE EXECUÇÃO (browser/socket/timeout). Allowlist: só isto autoriza retry.
FLAKE_RE='Playwright|playwright|browserType\.launch|Executable doesn.?t exist|Target (page|closed|page, context)|browser has been closed|WebSocket|net::ERR|ECONNREFUSED|Connection (closed|refused|reset)|Could not connect|Failed to connect|[Tt]imeout.*(exceeded|waiting)|timed out|Navigation.*timeout|Alvo visual não ficou disponível'

run_pest() {
  # tee: mantém o log VISÍVEL no CI (não engole a saída) e captura pra classificação.
  ./vendor/bin/pest "$@" 2>&1 | tee "$LOG"
  return "${PIPESTATUS[0]}"
}

run_pest "$@"
code=$?
[ "$code" -eq 0 ] && exit 0

out="$(cat "$LOG" 2>/dev/null || true)"

if grep -qE "$PIXEL_DIFF_RE" <<<"$out"; then
  echo "::notice::visreg-flake-retry: falha é REGRESSÃO DE PIXEL (double-threshold) — NÃO re-roda. Gate enforcing falha como esperado (exit ${code})."
  exit "$code"
fi

if grep -qE "$FLAKE_RE" <<<"$out"; then
  echo "::warning::visreg-flake-retry: assinatura de FLAKE de execução (browser/socket/timeout) — re-rodando UMA vez."
  run_pest "$@"
  exit $?
fi

echo "::notice::visreg-flake-retry: falha SEM assinatura de flake conhecida — NÃO re-roda (conservador, não mascara falha real). exit ${code}."
exit "$code"
