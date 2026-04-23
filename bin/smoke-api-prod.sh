#!/usr/bin/env bash
#
# Smoke test da API Passport em producao (oimpresso.com).
# Valida o contrato que os clientes desktop consomem.
#
# Uso:
#   bin/smoke-api-prod.sh
#
# Opcional — testar password grant tambem:
#   API_CLIENT_ID=<uuid> API_CLIENT_SECRET=<secret> \
#   API_USERNAME=<email> API_PASSWORD=<pass> \
#   bin/smoke-api-prod.sh
#
# Saida: HTTP status por endpoint + verdicts PASS/FAIL.

set -u

HOST="${HOST:-https://oimpresso.com}"
PASS=0
FAIL=0

check() {
  local label="$1"
  local expected="$2"
  local actual="$3"
  if [[ "$actual" == "$expected" ]]; then
    echo "  PASS  $label (HTTP $actual)"
    PASS=$((PASS+1))
  else
    echo "  FAIL  $label (esperado HTTP $expected, got $actual)"
    FAIL=$((FAIL+1))
  fi
}

echo "=== Smoke test API Passport — $HOST ==="

# 1. /api/user sem token deve dar 401
code=$(curl -4 -sS -o /dev/null -w "%{http_code}" \
  -H "Accept: application/json" \
  "$HOST/api/user")
check "/api/user sem token → 401" "401" "$code"

# 2. /api/officeimpresso sem token deve dar 401 (modulo ativo) ou 404 (modulo inativo)
code=$(curl -4 -sS -o /dev/null -w "%{http_code}" \
  -H "Accept: application/json" \
  "$HOST/api/officeimpresso")
check "/api/officeimpresso sem token → 401" "401" "$code"

# 3. /oauth/token sem grant_type deve dar 400 unsupported_grant_type
body=$(curl -4 -sS -X POST \
  -H "Accept: application/json" \
  "$HOST/oauth/token")
code=$(curl -4 -sS -o /dev/null -w "%{http_code}" -X POST \
  -H "Accept: application/json" \
  "$HOST/oauth/token")
check "/oauth/token sem body → 400" "400" "$code"

if echo "$body" | grep -q '"unsupported_grant_type"'; then
  echo "  PASS  /oauth/token responde error=unsupported_grant_type"
  PASS=$((PASS+1))
else
  echo "  FAIL  /oauth/token body nao tem error=unsupported_grant_type: $body"
  FAIL=$((FAIL+1))
fi

# 4. Password grant end-to-end (requer creds)
if [[ -n "${API_CLIENT_ID:-}" && -n "${API_CLIENT_SECRET:-}" && -n "${API_USERNAME:-}" && -n "${API_PASSWORD:-}" ]]; then
  echo ""
  echo "=== Password grant (real) ==="

  response=$(curl -4 -sS -X POST \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    "$HOST/oauth/token" \
    -d "{\"grant_type\":\"password\",\"client_id\":\"$API_CLIENT_ID\",\"client_secret\":\"$API_CLIENT_SECRET\",\"username\":\"$API_USERNAME\",\"password\":\"$API_PASSWORD\",\"scope\":\"*\"}")

  token=$(echo "$response" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

  if [[ -n "$token" ]]; then
    echo "  PASS  access_token obtido (${#token} chars)"
    PASS=$((PASS+1))

    code=$(curl -4 -sS -o /dev/null -w "%{http_code}" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer $token" \
      "$HOST/api/user")
    check "/api/user com Bearer → 200" "200" "$code"

    code=$(curl -4 -sS -o /dev/null -w "%{http_code}" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer $token" \
      "$HOST/api/officeimpresso")
    check "/api/officeimpresso com Bearer → 200" "200" "$code"
  else
    echo "  FAIL  password grant nao retornou access_token: $response"
    FAIL=$((FAIL+1))
  fi
else
  echo ""
  echo "(skipping password grant — exporte API_CLIENT_ID/SECRET/USERNAME/PASSWORD pra testar full flow)"
fi

echo ""
echo "=== $PASS PASS · $FAIL FAIL ==="
[[ $FAIL -eq 0 ]]
