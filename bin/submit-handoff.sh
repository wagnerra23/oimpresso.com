#!/usr/bin/env bash
# Transporte do loop de handoff zero-paste (Fase 0 · ADR 0283/0285) — sign + POST.
#
# Fonte ÚNICA do "assina (HMAC via bin/sign-handoff.php) → POST no tool MCP
# handoff-submit → pending". Reusada por DOIS caminhos de transporte:
#   1) handoff-sign-submit.yml — commit MANUAL de [W] em prototipo-ui/handoffs/ (o push dispara).
#   2) cowork-inbox.yml         — PUBLISHER Cowork→repo (PR-7 · ADR 0285): o Cowork dropa em
#      cowork-inbox/, a Action pousa em prototipo-ui/handoffs/ e chama ISTO inline —
#      porque o auto-merge feito com GITHUB_TOKEN NÃO dispara o on-push do (1)
#      (regra do GitHub: eventos do GITHUB_TOKEN não disparam outros workflows).
#
# O segredo vive SÓ no servidor/CI (HANDOFF_SECRET) — o Cowork nunca assina
# (proibição ADR 0283). NÃO faz auto-merge: só cria pending. O merge do CÓDIGO é
# o 1-clique de [W].
#
# Uso:
#   HANDOFF_SECRET=… SUBMIT_TOKEN=… [MCP_ENDPOINT=…] bash bin/submit-handoff.sh <file.md> [<file.md> …]
#   bash bin/submit-handoff.sh --self-test     # controle-negativo (sem segredo/rede/php)
#
# Degrada pra skip-as-pass (exit 0) enquanto os secrets não existirem (advisory,
# ADR 0271/0275). Exit: 0 OK/skip/selftest-verde · 1 falha de submit (sig/scope/rede)
# ou self-test vermelho.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SIGNER="${SCRIPT_DIR}/sign-handoff.php"
MCP_ENDPOINT="${MCP_ENDPOINT:-https://mcp.oimpresso.com/api/mcp}"

# Assina (bin/sign-handoff.php) e submete UM arquivo. Ecoa status. Retorna 0/1.
submit_one() {
  local f="$1"
  if [ ! -f "$f" ]; then
    echo "ℹ️  $f não existe no working tree — pulado."
    return 0
  fi
  echo "── $f ──"

  local body
  if ! body="$(php "$SIGNER" --file="$f")"; then
    echo "🔴 falha ao assinar $f"
    return 1
  fi

  # POST stateless no endpoint MCP (Mcp::web é síncrono — JSON de volta).
  local resp code is_err
  resp="$(mktemp)"
  code="$(curl -sS -o "$resp" -w '%{http_code}' -X POST "${MCP_ENDPOINT}" \
    -H "Authorization: Bearer ${SUBMIT_TOKEN}" \
    -H 'Content-Type: application/json' \
    -H 'Accept: application/json, text/event-stream' \
    --data-binary "$body" || echo "000")"

  # isError no resultado JSON-RPC (tool errou: sig inválida, sem scope…).
  is_err="$(jq -r '.result.isError // (.error != null)' "$resp" 2>/dev/null || echo "true")"

  if [ "$code" != "200" ] || [ "$is_err" = "true" ]; then
    echo "🔴 submit falhou (HTTP $code · isError=$is_err):"
    jq -r '.result.content[0].text // .error.message // .' "$resp" 2>/dev/null || cat "$resp"
    rm -f "$resp"
    return 1
  fi
  echo "🟢 submetido → pending:"
  jq -r '.result.content[0].text // empty' "$resp" 2>/dev/null || true
  rm -f "$resp"
  return 0
}

run() {
  # Skip-as-pass enquanto [W] não configurou os secrets (advisory).
  if [ -z "${HANDOFF_SECRET:-}" ] || [ -z "${SUBMIT_TOKEN:-}" ]; then
    echo "ℹ️  HANDOFF_SECRET/SUBMIT_TOKEN ainda não configurados — skip-as-pass."
    echo "    Configure-os (Settings → Secrets) pra ligar o transporte zero-paste (ADR 0283/0285)."
    return 0
  fi

  if [ "$#" -eq 0 ]; then
    echo "Nenhum handoff pra submeter — nada a fazer."
    return 0
  fi

  local fail=0 f
  for f in "$@"; do
    submit_one "$f" || fail=1
  done

  if [ "$fail" -ne 0 ]; then
    echo ""
    echo "❌ Um ou mais handoffs não entraram. Confira HANDOFF_SECRET (tem que bater o do servidor),"
    echo "   o scope do SUBMIT_TOKEN (jana.mcp.handoff.submit) e o MCP_ENDPOINT."
    return 1
  fi
  echo "✅ Handoffs submetidos → pending (sem auto-merge — merge é o 1-clique do [W])."
  return 0
}

# Controle-negativo: exercita os 3 guard-rails SEM tocar php/curl/rede.
self_test() {
  local fails=() out rc self="${BASH_SOURCE[0]}"

  # 1) sem segredo → skip-as-pass (exit 0), antes de qualquer rede.
  set +e
  out="$(HANDOFF_SECRET='' SUBMIT_TOKEN='' bash "$self" dummy.md 2>&1)"; rc=$?
  set -e
  if [ "$rc" -ne 0 ] || ! grep -q 'skip-as-pass' <<<"$out"; then
    fails+=("SKIP-AS-PASS falhou: rc=$rc | $out")
  fi

  # 2) com segredo fake, sem arquivos → exit 0 "nada a fazer" (sem rede).
  set +e
  out="$(HANDOFF_SECRET='x' SUBMIT_TOKEN='y' bash "$self" 2>&1)"; rc=$?
  set -e
  if [ "$rc" -ne 0 ] || ! grep -q 'nada a fazer' <<<"$out"; then
    fails+=("VAZIO falhou: rc=$rc | $out")
  fi

  # 3) com segredo fake + arquivo inexistente → pulado, exit 0 (sem rede).
  set +e
  out="$(HANDOFF_SECRET='x' SUBMIT_TOKEN='y' bash "$self" /nao/existe/foo.md 2>&1)"; rc=$?
  set -e
  if [ "$rc" -ne 0 ] || ! grep -q 'não existe no working tree' <<<"$out"; then
    fails+=("INEXISTENTE falhou: rc=$rc | $out")
  fi

  if [ "${#fails[@]}" -ne 0 ]; then
    printf 'submit-handoff SELF-TEST 🔴\n'
    printf '%s\n' "${fails[@]}"
    return 1
  fi
  echo "submit-handoff SELF-TEST 🟢 (skip-as-pass · vazio · arquivo inexistente — sem rede)"
  return 0
}

if [ "${1:-}" = "--self-test" ]; then
  self_test
  exit $?
fi

run "$@"
