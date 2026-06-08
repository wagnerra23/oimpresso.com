-- ============================================================================
-- 04 — UPSERT staging → contacts (rodar no MySQL prod) — CORE
-- ============================================================================
--
-- 🚨 OBRIGATÓRIO substituir ${BIZ_ID} antes de rodar:
--    Wagner WR2 = 1
--    Larissa ROTA LIVRE = 4
--    Martinho Caçambas = 164
--    Outros = consultar `SELECT id, name FROM business`
--
-- ⚠️ Multi-tenant Tier 0 ([ADR 0093]):
--    ${BIZ_ID} errado = vaza clientes pra tenant errado = pior bug possível.
--    SEMPRE confirma antes via:
--      SELECT id, name FROM business WHERE id = ${BIZ_ID};
--
-- Estratégia: INSERT ... ON DUPLICATE KEY UPDATE pela chave composta
--             (business_id, legacy_id). Migration `add_legacy_id_to_contacts`
--             já criou esse índice composto.
--
-- Idempotente: rerun não duplica, atualiza campos mudados desde último import.
--
-- created_by = 1 (system/admin) — Wagner consegue trocar via SELECT user_id
-- ============================================================================

-- Backup de segurança (rodar UMA VEZ antes do INSERT real, no shell):
--   mysqldump -h prod -u admin -p oimpresso contacts \
--     --where="business_id = ${BIZ_ID}" \
--     > contacts-pre-import-biz${BIZ_ID}-$(date +%Y%m%d-%H%M%S).sql

-- Confere business existe e bate com o cliente esperado:
SELECT id, name FROM business WHERE id = ${BIZ_ID};
-- ⚠️ PARE AQUI se nome não bate. Não continue.

-- Contagem inicial pra diff posterior:
SELECT
    (SELECT COUNT(*) FROM contacts_staging_pessoas)                                     AS staging_rows,
    (SELECT COUNT(*) FROM contacts WHERE business_id = ${BIZ_ID})                       AS contacts_before,
    (SELECT COUNT(*) FROM contacts WHERE business_id = ${BIZ_ID} AND legacy_id IS NOT NULL) AS already_imported;

-- ============================================================================
-- UPSERT principal
-- ============================================================================

INSERT INTO contacts (
    business_id,
    type,
    name,
    supplier_business_name,
    tax_number,
    mobile,
    alternate_number,
    email,
    city,
    contact_status,
    legacy_id,
    contact_id,
    created_by,
    created_at,
    updated_at
)
SELECT
    ${BIZ_ID}                                                              AS business_id,
    'customer'                                                             AS type,
    TRIM(IFNULL(s.razao_social, CONCAT('Cliente legacy ', s.legacy_codigo))) AS name,
    TRIM(s.razao_social)                                                   AS supplier_business_name,
    NULLIF(s.cpf_cnpj, '')                                                 AS tax_number,
    NULLIF(s.telefone_principal, '')                                       AS mobile,
    NULLIF(s.telefone_alternativo, '')                                     AS alternate_number,
    NULLIF(s.email, '')                                                    AS email,
    TRIM(s.cidade)                                                         AS city,
    CASE
        WHEN UPPER(IFNULL(s.bloqueado_sn, 'N')) = 'S' THEN 'inactive'
        ELSE 'active'
    END                                                                    AS contact_status,
    s.legacy_codigo                                                        AS legacy_id,
    CONCAT('FB-', s.legacy_codigo)                                         AS contact_id,
    1                                                                      AS created_by,
    IFNULL(s.data_cadastro, NOW())                                         AS created_at,
    NOW()                                                                  AS updated_at
FROM contacts_staging_pessoas s
ON DUPLICATE KEY UPDATE
    name                   = VALUES(name),
    supplier_business_name = VALUES(supplier_business_name),
    tax_number             = VALUES(tax_number),
    mobile                 = VALUES(mobile),
    alternate_number       = VALUES(alternate_number),
    email                  = VALUES(email),
    city                   = VALUES(city),
    contact_status         = VALUES(contact_status),
    updated_at             = NOW();

-- ============================================================================
-- Contagem final pra diff
-- ============================================================================

SELECT
    (SELECT COUNT(*) FROM contacts WHERE business_id = ${BIZ_ID})                       AS contacts_after,
    (SELECT COUNT(*) FROM contacts WHERE business_id = ${BIZ_ID} AND legacy_id IS NOT NULL) AS with_legacy_id_after;

-- ============================================================================
-- ⚠️ Cleanup staging (rodar quando confirmado import OK):
--   DROP TABLE contacts_staging_pessoas;
-- ============================================================================
