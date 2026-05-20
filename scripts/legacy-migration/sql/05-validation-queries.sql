-- ============================================================================
-- 05 — VALIDAÇÃO pós-import (rodar no MySQL prod APÓS UPSERT)
-- ============================================================================
--
-- 🚨 Substituir ${BIZ_ID} antes de rodar.
--
-- 7 queries de validação. Roda todas. Qualquer FAIL = investigar antes de
-- considerar migração concluída.
-- ============================================================================

-- ── Q1: Diff de contagem (staging vs contacts importado) ────────────────────
SELECT
    'staging'   AS source, COUNT(*) AS rows_count FROM contacts_staging_pessoas
UNION ALL
SELECT
    'imported'  AS source, COUNT(*)               FROM contacts
    WHERE business_id = ${BIZ_ID} AND legacy_id IS NOT NULL;
-- EXPECTED: imported >= staging (>= porque contacts pode ter legacy_id de imports anteriores)

-- ── Q2: contact_status bate com BLOQUEADO Firebird? ─────────────────────────
SELECT
    s.bloqueado_sn,
    c.contact_status,
    COUNT(*) AS cnt
FROM contacts_staging_pessoas s
JOIN contacts c ON c.business_id = ${BIZ_ID} AND c.legacy_id = s.legacy_codigo
GROUP BY s.bloqueado_sn, c.contact_status
ORDER BY cnt DESC;
-- EXPECTED:
--   'N' + 'active'   = maioria
--   'S' + 'inactive' = bloqueados
--   QUALQUER 'N' + 'inactive' OU 'S' + 'active' = BUG mapping

-- ── Q3: Duplicatas tax_number no business (Delphi não tinha UNIQUE) ────────
SELECT
    tax_number,
    COUNT(*) AS qtd,
    GROUP_CONCAT(legacy_id ORDER BY legacy_id) AS legacy_codigos
FROM contacts
WHERE business_id = ${BIZ_ID}
  AND tax_number IS NOT NULL
  AND tax_number <> ''
GROUP BY tax_number
HAVING COUNT(*) > 1
ORDER BY qtd DESC
LIMIT 50;
-- EXPECTED: 0 rows ideal. Se há duplicatas, Wagner decide consolidar manualmente.

-- ── Q4: Clientes sem CNPJ/CPF (legado tinha buracos) ────────────────────────
SELECT COUNT(*) AS sem_doc
FROM contacts
WHERE business_id = ${BIZ_ID}
  AND legacy_id IS NOT NULL
  AND (tax_number IS NULL OR tax_number = '');
-- EXPECTED: ~0%. Se >5%, talvez vale revisar CSV.

-- ── Q5: Sample 10 mais recentes pra eyeball ─────────────────────────────────
SELECT
    id, business_id, legacy_id, contact_id, name, tax_number, mobile, email, city, contact_status, created_at
FROM contacts
WHERE business_id = ${BIZ_ID}
  AND legacy_id IS NOT NULL
ORDER BY id DESC
LIMIT 10;

-- ── Q6: Cross-tenant check — Tier 0 paranoia (ADR 0093) ────────────────────
SELECT
    business_id,
    COUNT(*) AS rows_with_this_legacy_id
FROM contacts
WHERE legacy_id IN (SELECT legacy_codigo FROM contacts_staging_pessoas LIMIT 5)
GROUP BY business_id;
-- EXPECTED: só business_id = ${BIZ_ID}. Outro bizID = LEAK CROSS-TENANT, parar tudo.

-- ── Q7: Cobertura — quantos % do staging foi efetivamente importado ────────
SELECT
    s.staging_rows,
    i.imported_rows,
    ROUND(i.imported_rows / s.staging_rows * 100, 2) AS pct_cobertura
FROM
    (SELECT COUNT(*) AS staging_rows FROM contacts_staging_pessoas) s,
    (SELECT COUNT(*) AS imported_rows FROM contacts
       WHERE business_id = ${BIZ_ID}
         AND legacy_id IN (SELECT legacy_codigo FROM contacts_staging_pessoas)
    ) i;
-- EXPECTED: 100%. Se <100%, alguns rows do staging falharam UPSERT (provavelmente
--           legacy_codigo NULL ou conflito de PK).

-- ============================================================================
-- Se TUDO passou, cleanup staging:
--   DROP TABLE contacts_staging_pessoas;
--
-- E gravar snapshot canônico:
--   memory/sessions/YYYY-MM-DD-migracao-clientes-biz-${BIZ_ID}.md
--   com contagem antes/depois + cliente + data.
-- ============================================================================
