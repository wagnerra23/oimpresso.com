#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Popula o banco de STAGING (MariaDB oimpresso-staging-db) com dump ANONIMIZADO
# de produção. Roda no HOST CT 100.
#
#   1. mariadb-dump prod (MariaDB) → import staging-db  [pipe, sem arquivo cru]
#   2. anonymize.sql (PII → fake; credenciais/conversas → truncate)
#   3. reset senha de todos os users → 'staging2026'
#   4. validação 0-PII (ABORTA se sobrar PII real)
#
# Prod é MariaDB 11.8 → usamos mariadb-dump (cliente nativo) e o staging também
# é MariaDB → import limpo (sem incompatibilidade de collation MySQL↔MariaDB).
#
# Uso: bash docker/oimpresso-staging/seed-from-prod.sh
# Ref: memory/reference/lgpd-mapa-tratamento.md + ADR 0235
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

CODE=/opt/oimpresso-staging/code
SQLDIR="$CODE/docker/oimpresso-staging"
MCP_ENV=/opt/oimpresso-mcp/code/.env

# Produção (read-only) — credenciais do .env do MCP (já conecta no Hostinger)
PHOST=$(grep '^DB_HOST=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PDB=$(grep '^DB_DATABASE=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PUSER=$(grep '^DB_USERNAME=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PPASS=$(grep '^DB_PASSWORD=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
# staging-db (MariaDB) root
SROOT=$(cat /opt/oimpresso-staging/.db-root-pwd)

echo "==> 1/4  mariadb-dump prod ($PDB @ $PHOST) -> oimpresso-staging-db  [pipe]"
docker run --rm --network docker-host_default \
  -e PHOST="$PHOST" -e PUSER="$PUSER" -e PPASS="$PPASS" -e PDB="$PDB" mariadb:11 bash -c '
    mariadb-dump --host="$PHOST" --user="$PUSER" --password="$PPASS" \
      --single-transaction --skip-lock-tables --no-tablespaces \
      "$PDB"
  ' 2>/tmp/staging-dump.err \
  | docker exec -i -e SROOT="$SROOT" oimpresso-staging-db bash -c 'mariadb --user=root --password="$SROOT" oimpresso_staging'
echo "    dump stderr ($(grep -vci 'using a password' /tmp/staging-dump.err) linhas relevantes):"
grep -vi "using a password" /tmp/staging-dump.err | tail -3 || true
TBLS=$(docker exec -e SROOT="$SROOT" oimpresso-staging-db bash -c 'mariadb --user=root --password="$SROOT" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"oimpresso_staging\";"' 2>/dev/null | tr -d '[:space:]')
echo "    tabelas importadas: $TBLS"
[ "${TBLS:-0}" -lt 50 ] && { echo "!! import suspeito (<50 tabelas). Abortando."; exit 1; } || true

echo "==> 2/4  anonymize.sql"
docker exec -i -e SROOT="$SROOT" oimpresso-staging-db bash -c 'mariadb --force --user=root --password="$SROOT" oimpresso_staging' < "$SQLDIR/anonymize.sql" 2>&1 | grep -vi "using a password" | tail -3

echo "==> 3/4  reset senha (bcrypt staging2026)"
HASH=$(docker run --rm --entrypoint php oimpresso/mcp:latest -r "echo password_hash('staging2026', PASSWORD_BCRYPT);")
docker exec -e SROOT="$SROOT" -e HASH="$HASH" oimpresso-staging-db bash -c 'mariadb --user=root --password="$SROOT" oimpresso_staging -e "UPDATE users SET password=\"$HASH\" WHERE password IS NOT NULL;"' 2>&1 | grep -vi "using a password" || true
echo "    senha resetada (hash ${HASH:0:12}...)"

echo "==> 4/4  VALIDACAO 0-PII"
docker exec -e SROOT="$SROOT" oimpresso-staging-db bash -c 'mariadb --user=root --password="$SROOT" oimpresso_staging -N -e "
SELECT CONCAT(\"contacts_email_real=\", COUNT(*)) FROM contacts WHERE email IS NOT NULL AND email<>\"\" AND email <> CONCAT(\"contato\",id,\"@staging.local\");
SELECT CONCAT(\"contacts_cpf_real=\",   COUNT(*)) FROM contacts WHERE cpf_cnpj IS NOT NULL AND cpf_cnpj<>\"\" AND cpf_cnpj <> LPAD(id,14,\"0\");
SELECT CONCAT(\"users_email_real=\",    COUNT(*)) FROM users    WHERE email IS NOT NULL AND email<>\"\" AND email <> CONCAT(\"user\",id,\"@staging.local\");
SELECT CONCAT(\"wa_messages_left=\",    COUNT(*)) FROM whatsapp_messages;
SELECT CONCAT(\"boleto_creds_left=\",   COUNT(*)) FROM rb_boleto_credentials;
SELECT CONCAT(\"nfe_certs_left=\",      COUNT(*)) FROM nfe_certificados;
"' 2>&1 | grep -vi "using a password" | tee /tmp/staging-pii-check.txt
if grep -qE "=([1-9][0-9]*)$" /tmp/staging-pii-check.txt; then
  echo "!! FALHA: ainda ha PII real. NAO liberar. Revisar anonymize.sql."; exit 1
fi
echo "SEED_DONE — staging MariaDB anonimizado, 0 PII real"
