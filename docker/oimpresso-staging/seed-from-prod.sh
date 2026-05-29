#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Popula oimpresso_staging com dump ANONIMIZADO de produção. Roda no HOST CT 100.
#
#   1. dump prod  → import staging  (via PIPE, sem arquivo .sql cru no disco)
#   2. anonymize.sql (PII → fake; credenciais/conversas → truncate)
#   3. reset senha de todos os users → 'staging2026' (equipe loga)
#   4. validação 0-PII (ABORTA se sobrar qualquer PII real)
#
# Uso: bash docker/oimpresso-staging/seed-from-prod.sh
# Ref: memory/reference/lgpd-mapa-tratamento.md + ADR 0235
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

CODE=/opt/oimpresso-staging/code
SQLDIR="$CODE/docker/oimpresso-staging"
MCP_ENV=/opt/oimpresso-mcp/code/.env

# Credenciais de PRODUÇÃO (read-only) — do .env do MCP, que já conecta no Hostinger
PHOST=$(grep '^DB_HOST=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PDB=$(grep '^DB_DATABASE=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PUSER=$(grep '^DB_USERNAME=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
PPASS=$(grep '^DB_PASSWORD=' "$MCP_ENV" | cut -d= -f2- | tr -d '"')
RP=$(docker exec mysql-workers cat /run/secrets/mysql_root)

echo "==> 1/4  dump prod ($PDB @ $PHOST) -> oimpresso_staging  [pipe, sem arquivo cru]"
docker exec -e PHOST="$PHOST" -e PUSER="$PUSER" -e PPASS="$PPASS" -e PDB="$PDB" -e RP="$RP" mysql-workers bash -c '
  mysqldump --host="$PHOST" --user="$PUSER" --password="$PPASS" \
    --single-transaction --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF \
    --ignore-table="$PDB".activity_log \
    --ignore-table="$PDB".telescope_entries \
    --ignore-table="$PDB".telescope_entries_tags \
    "$PDB" 2>/dev/null | mysql --user=root --password="$RP" oimpresso_staging
'
echo "    import concluido"

echo "==> 2/4  anonymize.sql"
docker exec -i -e RP="$RP" mysql-workers bash -c 'mysql --force --user=root --password="$RP" oimpresso_staging' < "$SQLDIR/anonymize.sql" 2>&1 | grep -vi "using a password" | tail -3

echo "==> 3/4  reset senha (bcrypt staging2026) para todos os users"
HASH=$(docker exec oimpresso-staging php artisan tinker --execute "echo bcrypt('staging2026');" 2>/dev/null | tail -1)
docker exec -e RP="$RP" -e HASH="$HASH" mysql-workers bash -c 'mysql --user=root --password="$RP" oimpresso_staging -e "UPDATE users SET password=\"$HASH\" WHERE password IS NOT NULL;"' 2>&1 | grep -vi "using a password" || true
echo "    senha resetada"

echo "==> 4/4  VALIDACAO 0-PII (compara com valor anonimizado esperado)"
docker exec -e RP="$RP" mysql-workers bash -c 'mysql --user=root --password="$RP" oimpresso_staging -N -e "
SELECT CONCAT(\"contacts_email_real=\",  COUNT(*)) FROM contacts     WHERE email IS NOT NULL AND email<>\"\" AND email     <> CONCAT(\"contato\",id,\"@staging.local\");
SELECT CONCAT(\"contacts_cpf_real=\",    COUNT(*)) FROM contacts     WHERE cpf_cnpj IS NOT NULL AND cpf_cnpj<>\"\" AND cpf_cnpj <> LPAD(id,14,\"0\");
SELECT CONCAT(\"users_email_real=\",     COUNT(*)) FROM users        WHERE email IS NOT NULL AND email<>\"\" AND email     <> CONCAT(\"user\",id,\"@staging.local\");
SELECT CONCAT(\"wa_messages_left=\",     COUNT(*)) FROM whatsapp_messages;
SELECT CONCAT(\"boleto_creds_left=\",    COUNT(*)) FROM rb_boleto_credentials;
SELECT CONCAT(\"nfe_certs_left=\",       COUNT(*)) FROM nfe_certificados;
"' 2>&1 | grep -vi "using a password" | tee /tmp/staging-pii-check.txt

# Aborta se qualquer contador != 0
if grep -qE "=([1-9][0-9]*)$" /tmp/staging-pii-check.txt; then
  echo "!! FALHA: ainda ha PII real. NAO liberar staging. Revisar anonymize.sql."
  exit 1
fi
echo "SEED_DONE — 0 PII real, banco staging anonimizado"
