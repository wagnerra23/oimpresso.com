-- ============================================================================
-- 00 — PREFLIGHT CHECKS (rodar no MySQL prod ANTES do passo 02)
-- ============================================================================
--
-- 6 verificações que economizam horas:
--   1. business_id=164 existe e é Martinho
--   2. Tabelas destino existem (fin_titulos, fin_titulo_baixas, fin_contas_bancarias)
--   3. local_infile=ON (LOAD DATA não funciona sem)
--   4. contacts biz=164 tem rows (lookup cliente_id depende)
--   5. fin_contas_bancarias biz=164 tem pelo menos 1 conta (baixas precisam)
--   6. Backup recente existe
--
-- Qualquer FAIL = corrigir antes de seguir. Não pule.
-- ============================================================================

-- ── PC1: Business existe e bate ─────────────────────────────────────────────
SELECT id, name, created_at FROM business WHERE id = 164;
-- EXPECTED: 1 row, name='MARTINHO CAÇAMBAS LTDA' (ou similar).
-- FAIL se vazio OU outro nome → PARE.

-- ── PC2: Tabelas destino existem ────────────────────────────────────────────
SELECT TABLE_NAME, ENGINE, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'fin_titulos',
      'fin_titulo_baixas',
      'fin_contas_bancarias',
      'fin_planos_conta',
      'contacts',
      'business'
  )
ORDER BY TABLE_NAME;
-- EXPECTED: 6 rows. Se < 6 → migration faltando, rodar `php artisan migrate`.

-- ── PC3: local_infile = ON (LOAD DATA precisa) ──────────────────────────────
SHOW VARIABLES LIKE 'local_infile';
-- EXPECTED: Value='ON'.
-- FAIL: SET GLOBAL local_infile = 1;  (volátil, requer SUPER)
--       OU edita my.cnf [mysqld] local_infile=1 + restart.
-- Cliente também precisa flag: mysql --local-infile=1 ...

-- ── PC4: contacts biz=164 populado (lookup cliente_id depende) ──────────────
SELECT
    COUNT(*)                                                                AS total_contacts,
    SUM(CASE WHEN legacy_id  IS NOT NULL THEN 1 ELSE 0 END)                 AS com_legacy_id,
    SUM(CASE WHEN tax_number IS NOT NULL AND tax_number <> '' THEN 1 ELSE 0 END) AS com_tax_number
FROM contacts
WHERE business_id = 164;
-- EXPECTED: total ≈ 9.988 (handoff 2026-05-17). com_tax_number > 50%.
-- FAIL se total=0 → rodar pipeline `scripts/legacy-migration/sql/` (Clientes) primeiro.

-- ── PC5: fin_contas_bancarias biz=164 tem default ──────────────────────────
SELECT id, nome, tipo, moeda, business_id
FROM fin_contas_bancarias
WHERE business_id = 164
ORDER BY id ASC;
-- EXPECTED: ≥ 1 row. Primeira ativa vira conta_default das baixas no passo 04.
-- FAIL se vazio → criar:
--   INSERT INTO fin_contas_bancarias (business_id, nome, tipo, moeda, created_by, created_at, updated_at)
--   VALUES (164, 'Conta Default Importação', 'corrente', 'BRL', 1, NOW(), NOW());

-- ── PC6: Estado atual fin_titulos biz=164 (snapshot pré-import) ────────────
SELECT
    COUNT(*)                                                       AS titulos_existentes,
    SUM(CASE WHEN origem = 'manual' AND origem_id < 0 THEN 1 ELSE 0 END) AS legacy_negativos,
    SUM(CASE WHEN status = 'aberto'  THEN 1 ELSE 0 END)            AS abertos,
    SUM(CASE WHEN status = 'quitado' THEN 1 ELSE 0 END)            AS quitados,
    MIN(created_at)                                                AS primeiro_import,
    MAX(updated_at)                                                AS ultimo_update
FROM fin_titulos
WHERE business_id = 164;
-- EXPECTED (handoff 2026-05-17): titulos_existentes ≈ 83.107.
-- Anotar este snapshot ANTES do import pra comparar com Q1 do passo 05.

-- ── PC7: Backup recente existe (verifica filesystem manualmente) ──────────
-- ⚠️ Esta query só LEMBRA — execução é shell:
--   mysqldump -h prod -u admin -p oimpresso fin_titulos fin_titulo_baixas \
--     --where="business_id=164" \
--     > backup-martinho-financeiro-$(date +%Y%m%d-%H%M%S).sql
--
-- Após rodar:
--   ls -lh backup-martinho-financeiro-*.sql  -- confirma > 0 bytes
--
-- Sem backup recente (< 24h) NÃO prosseguir. Risco: import com bug, sem rollback.

-- ============================================================================
-- Se TUDO PC1-PC7 verde, prosseguir pro passo 02 (create-staging-table).
-- Se qualquer FAIL: corrigir + repetir TODO o preflight.
-- ============================================================================
