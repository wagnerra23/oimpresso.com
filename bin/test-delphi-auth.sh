#!/usr/bin/env bash
#
# Simula exatamente o que o Delphi faz pra autenticar.
# Se isto funcionar mas o Delphi nao, o problema e no Delphi (conectividade,
# config errada, time/SSL). Se isto falhar, descobrimos o erro.
#
# Uso:
#   bin/test-delphi-auth.sh WR23 "Wscrct*2312"          # username + senha como args
#   DELPHI_USER=WR23 DELPHI_PASS='xxx' bin/test-delphi-auth.sh
#
# Pre-req: conseguir ssh no servidor pra pegar client_secret. Se ja tiver,
#   export DELPHI_CLIENT_ID=3 DELPHI_CLIENT_SECRET=abc antes.

set -u

HOST="${HOST:-https://oimpresso.com}"
USER="${1:-${DELPHI_USER:-}}"
PASS="${2:-${DELPHI_PASS:-}}"

if [[ -z "$USER" || -z "$PASS" ]]; then
    echo "Uso: $0 <username> <password>"
    echo "     ou exporte DELPHI_USER e DELPHI_PASS"
    exit 1
fi

# Pega client_id/secret do servidor se nao foram passados
if [[ -z "${DELPHI_CLIENT_ID:-}" || -z "${DELPHI_CLIENT_SECRET:-}" ]]; then
    echo "==> Buscando client_id/secret no servidor..."
    CREDS=$(ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 -o ConnectTimeout=60 u906587222@148.135.133.115 \
        'cd domains/oimpresso.com/public_html && php -r "
            require \"vendor/autoload.php\";
            \$app = require \"bootstrap/app.php\";
            \$app->make(\"Illuminate\\Contracts\\Console\\Kernel\")->bootstrap();
            \$c = \\DB::table(\"oauth_clients\")
                    ->where(\"password_client\", 1)
                    ->where(\"revoked\", 0)
                    ->orderBy(\"id\")
                    ->first([\"id\", \"secret\", \"name\"]);
            if (!\$c) { echo \"ERR_NO_CLIENT\"; exit(1); }
            echo \$c->id . \"|\" . \$c->secret . \"|\" . \$c->name;
        "' 2>/dev/null)
    if [[ "$CREDS" == *"ERR"* || -z "$CREDS" ]]; then
        echo "FALHA: nao conseguiu obter client. Passe via env: DELPHI_CLIENT_ID/SECRET"
        exit 1
    fi
    DELPHI_CLIENT_ID="${CREDS%%|*}"
    DELPHI_CLIENT_SECRET=$(echo "$CREDS" | awk -F'|' '{print $2}')
    CLIENT_NAME=$(echo "$CREDS" | awk -F'|' '{print $3}')
    echo "    client_id=$DELPHI_CLIENT_ID ($CLIENT_NAME)"
fi

echo ""
echo "==> POST $HOST/oauth/token (grant_type=password)"
RESPONSE=$(curl -4 -sS -w "\nHTTP_CODE:%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    "$HOST/oauth/token" \
    -d "{\"grant_type\":\"password\",\"client_id\":\"$DELPHI_CLIENT_ID\",\"client_secret\":\"$DELPHI_CLIENT_SECRET\",\"username\":\"$USER\",\"password\":\"$PASS\",\"scope\":\"*\"}")

HTTP_CODE=$(echo "$RESPONSE" | grep HTTP_CODE | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "HTTP $HTTP_CODE"
echo "$BODY" | head -c 500
echo ""

if [[ "$HTTP_CODE" != "200" ]]; then
    echo ""
    echo "FALHA — Delphi com essas creds tambem falharia."
    exit 1
fi

TOKEN=$(echo "$BODY" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
if [[ -z "$TOKEN" ]]; then
    echo "FALHA: access_token nao veio no body"
    exit 1
fi

echo ""
echo "==> GET $HOST/api/officeimpresso (com Bearer)"
curl -4 -sS -o /dev/null -w "  HTTP %{http_code}\n" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    "$HOST/api/officeimpresso"

echo ""
echo "==> Delphi pode autenticar com essas credenciais. Se o app nao esta"
echo "    conseguindo, o problema e client-side (ex.: URL errada, proxy,"
echo "    SSL nao validado, senha diferente, data/hora do Windows errada)."
