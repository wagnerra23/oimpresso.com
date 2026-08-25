#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUBMIT="$ROOT/bin/submit-handoff.sh"
FIX="$ROOT/bin/fixtures/submit-handoff"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
chmod +x "$FIX/fake-php.sh" "$FIX/fake-curl.sh" "$FIX/fake-jq.sh"
printf '%s\n' '# handoff fixture' > "$TMP/a.md"
printf '%s\n' '# handoff fixture 2' > "$TMP/b com espaço.md"

fails=0
check() { if eval "$2"; then echo "[OK] $1"; else echo "[FAIL] $1"; fails=$((fails + 1)); fi; }
run_case() {
  local scenario="$1"; shift
  : > "$TMP/request.log"; rm -f "$TMP/request.log.count"
  set +e
  OUT="$(HANDOFF_SECRET='segredo-que-nao-pode-vazar' SUBMIT_TOKEN='token-que-nao-pode-vazar' \
    PHP_BIN="$FIX/fake-php.sh" CURL_BIN="$FIX/fake-curl.sh" JQ_BIN="$FIX/fake-jq.sh" \
    FAKE_CURL_SCENARIO="$scenario" FAKE_CURL_LOG="$TMP/request.log" MCP_ENDPOINT='https://mcp.invalid/api/mcp' \
    bash "$SUBMIT" "$@" 2>&1)"
  RC=$?
  set -e
}

run_case success "$TMP/a.md"
check 'HTTP 200 + isError=false passa' '[ "$RC" -eq 0 ] && grep -q pending <<<"$OUT"' || true
if [ "$RC" -ne 0 ] || ! grep -q pending <<<"$OUT"; then printf '  saída: %s\n' "$OUT"; fi
check 'request usa POST, endpoint, bearer e content types' \
  'grep -q -- "-X" "$TMP/request.log" && grep -q -- "POST" "$TMP/request.log" && grep -q -- "https://mcp.invalid/api/mcp" "$TMP/request.log" && grep -q -- "Authorization: Bearer token-que-nao-pode-vazar" "$TMP/request.log" && grep -q -- "Content-Type: application/json" "$TMP/request.log"'
check 'body enviado é o envelope byte-idêntico do assinador fake' \
  'grep -Fq "BODY={\"jsonrpc\":\"2.0\"" "$TMP/request.log"'
check 'segredo e token nunca vazam no stdout/stderr' \
  '! grep -q "segredo-que-nao-pode-vazar\|token-que-nao-pode-vazar" <<<"$OUT"'

for scenario in iserror rpcerror invalid empty http401 http403 http422 http500 network; do
  run_case "$scenario" "$TMP/a.md"
  check "$scenario falha fechado" '[ "$RC" -eq 1 ] && grep -q "submit falhou\|não entraram" <<<"$OUT"'
done

run_case success "$TMP/a.md"; # signer falha é cenário separado, sem curl
: > "$TMP/request.log"; rm -f "$TMP/request.log.count"
set +e
OUT="$(HANDOFF_SECRET=x SUBMIT_TOKEN=y PHP_BIN="$FIX/fake-php.sh" CURL_BIN="$FIX/fake-curl.sh" JQ_BIN="$FIX/fake-jq.sh" \
  FAKE_SIGN_FAIL=1 FAKE_CURL_LOG="$TMP/request.log" bash "$SUBMIT" "$TMP/a.md" 2>&1)"; RC=$?
set -e
check 'falha do assinador impede qualquer POST' '[ "$RC" -eq 1 ] && [ ! -s "$TMP/request.log" ]'

run_case first-fail "$TMP/a.md" "$TMP/b com espaço.md"
check 'um handoff falha, mas os demais ainda são tentados e o total sai 1' \
  '[ "$RC" -eq 1 ] && [ "$(cat "$TMP/request.log.count")" -eq 2 ]'

if [ "$fails" -ne 0 ]; then echo "$fails falha(s) no transporte de handoff"; exit 1; fi
echo 'submit-handoff HTTP: todos os cenários passaram.'
