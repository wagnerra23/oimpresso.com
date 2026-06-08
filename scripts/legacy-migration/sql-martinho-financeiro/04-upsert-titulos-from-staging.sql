-- ============================================================================
-- 04 — UPSERT staging → fin_titulos + fin_titulo_baixas (MySQL prod) — CORE
-- ============================================================================
--
-- 🚨 biz_id = 164 HARD-CODED (Martinho Caçambas LTDA).
--    Pra outro cliente: copiar pasta + ajustar 164 → novo biz_id.
--
-- ⚠️ Multi-tenant Tier 0 (ADR 0093):
--    biz_id errado = vaza títulos R$$$ pra tenant errado = pior bug possível.
--    Q0 abaixo PARA execução se nome não bater.
--
-- Estratégia idempotente:
--   - fin_titulos:        ON DUPLICATE KEY UPDATE via UK (business_id, origem, origem_id, parcela_numero)
--                         origem='manual' + origem_id=-CODIGO (negativo evita colidir com transaction.id real)
--   - fin_titulo_baixas:  ON DUPLICATE KEY UPDATE via UK (business_id, idempotency_key)
--                         idempotency_key = 'leg-164-{CODIGO}' (max 36 char)
--
-- Backup ANTES (rodar UMA VEZ no shell, NÃO via SQL):
--   mysqldump -h prod -u admin -p oimpresso fin_titulos fin_titulo_baixas \
--     --where="business_id=164" \
--     > backup-martinho-financeiro-$(date +%Y%m%d-%H%M%S).sql
-- ============================================================================

-- ── Q0: Multi-tenant guard (PARE se nome não for Martinho) ──────────────────

SELECT id, name FROM business WHERE id = 164;
-- ⚠️ EXPECTED: id=164, name='MARTINHO CAÇAMBAS LTDA' (ou similar).
--    SE outro nome → PARE. Não rode os INSERTs abaixo.

-- Pré-flight contagens:
SELECT
    (SELECT COUNT(*) FROM fin_titulos_staging_martinho WHERE decision = 'import') AS staging_para_importar,
    (SELECT COUNT(*) FROM fin_titulos_staging_martinho WHERE decision = 'skip')   AS staging_skip,
    (SELECT COUNT(*) FROM fin_titulos      WHERE business_id = 164)               AS titulos_before,
    (SELECT COUNT(*) FROM fin_titulo_baixas WHERE business_id = 164)              AS baixas_before;

-- Conta bancária default (primeira ativa biz=164 — usada como fallback nas baixas)
SELECT @conta_default := id
FROM fin_contas_bancarias
WHERE business_id = 164
ORDER BY id ASC
LIMIT 1;

SELECT @conta_default AS conta_bancaria_default_id;
-- ⚠️ Se @conta_default for NULL: criar 1 fin_contas_bancarias biz=164 ANTES de continuar.
--    Q0b:
--      INSERT INTO fin_contas_bancarias (business_id, nome, tipo, moeda, created_by, created_at, updated_at)
--      VALUES (164, 'Conta Default Importação', 'corrente', 'BRL', 1, NOW(), NOW());

-- ============================================================================
-- 4.1 — UPSERT fin_titulos
-- ============================================================================

INSERT INTO fin_titulos (
    business_id,
    numero,
    tipo,
    status,
    cliente_id,
    cliente_descricao,
    valor_total,
    valor_aberto,
    moeda,
    emissao,
    vencimento,
    competencia_mes,
    origem,
    origem_id,
    parcela_numero,
    parcela_total,
    titulo_pai_id,
    plano_conta_id,
    categoria_id,
    observacoes,
    metadata,
    created_by,
    created_at,
    updated_at
)
SELECT
    164                                                                AS business_id,
    CONCAT('LEG-', s.legacy_codigo)                                    AS numero,
    s.tipo_normalized                                                  AS tipo,
    s.status_normalized                                                AS status,
    NULL                                                               AS cliente_id,         -- TODO: lookup contacts.legacy_id quando PESSOAS→contacts estiver completo
    LEFT(TRIM(IFNULL(s.razao_social, '')), 255)                        AS cliente_descricao,
    COALESCE(s.valor, 0)                                               AS valor_total,
    CASE
        WHEN s.status_normalized = 'quitado'   THEN 0
        WHEN s.status_normalized = 'cancelado' THEN 0
        ELSE COALESCE(s.valor, 0)
    END                                                                AS valor_aberto,
    'BRL'                                                              AS moeda,
    s.emissao_parsed                                                   AS emissao,
    s.vencto_parsed                                                    AS vencimento,
    s.competencia_mes                                                  AS competencia_mes,
    'manual'                                                           AS origem,
    -CAST(s.legacy_codigo AS SIGNED)                                   AS origem_id,          -- negativo: escapa conflito com transaction.id real
    s.parcela_numero                                                   AS parcela_numero,
    s.parcela_total                                                    AS parcela_total,
    NULL                                                               AS titulo_pai_id,
    NULL                                                               AS plano_conta_id,     -- TODO: lookup fin_planos_conta.legacy_id quando mapeado
    NULL                                                               AS categoria_id,
    LEFT(TRIM(IFNULL(s.historico, '')), 500)                           AS observacoes,
    JSON_OBJECT(
        'legacy_source',                  'wr-comercial-delphi',
        'legacy_id',                      s.legacy_codigo,
        'delphi_status_raw',              s.status_delphi,
        'delphi_tipo_raw',                s.tipo,
        'delphi_codpedido',               s.cod_pedido,
        'delphi_codempresa',              s.cod_empresa,
        'delphi_pessoa_responsavel',      s.pessoa_responsavel,
        'delphi_notafiscal_redacted',     CASE WHEN s.nota_fiscal     IS NOT NULL AND s.nota_fiscal     <> '' THEN '[REDACTED]' ELSE NULL END,
        'delphi_boleto_nosso_redacted',   CASE WHEN s.boleto_nosso_nr IS NOT NULL AND s.boleto_nosso_nr <> '' THEN '[REDACTED]' ELSE NULL END,
        'delphi_documento_redacted',      CASE WHEN s.documento       IS NOT NULL AND s.documento       <> '' THEN '[REDACTED]' ELSE NULL END,
        'delphi_juros',                   COALESCE(s.juros, 0),
        'delphi_desconto',                COALESCE(s.desconto, 0),
        'delphi_tipopagto',               s.tipo_pagto,
        'delphi_condicaopagto',           s.condicao_pagto,
        'delphi_cod_plano_contas',        s.cod_plano_contas,
        'delphi_cod_conta',               s.cod_conta,
        'is_write_off_candidate',         CASE WHEN s.is_write_off_candidate = 1 THEN CAST('true' AS JSON) ELSE CAST('false' AS JSON) END,
        'imported_at_iso',                DATE_FORMAT(NOW(), '%Y-%m-%dT%H:%i:%sZ'),
        'importer_version',               'sql-0.1.0'
    )                                                                  AS metadata,
    1                                                                  AS created_by,
    IFNULL(s.emissao_parsed, NOW())                                    AS created_at,
    NOW()                                                              AS updated_at
FROM fin_titulos_staging_martinho s
WHERE s.decision = 'import'
ON DUPLICATE KEY UPDATE
    numero            = VALUES(numero),
    tipo              = VALUES(tipo),
    status            = VALUES(status),
    cliente_descricao = VALUES(cliente_descricao),
    valor_total       = VALUES(valor_total),
    valor_aberto      = VALUES(valor_aberto),
    emissao           = VALUES(emissao),
    vencimento        = VALUES(vencimento),
    competencia_mes   = VALUES(competencia_mes),
    parcela_total     = VALUES(parcela_total),
    observacoes       = VALUES(observacoes),
    metadata          = VALUES(metadata),
    updated_at        = NOW();

-- ============================================================================
-- 4.2 — UPSERT fin_titulo_baixas (só pra status='quitado')
-- ============================================================================
--
-- Lookup titulo_id via (business_id, origem='manual', origem_id=-CODIGO, parcela_numero).
-- meio_pagamento sempre 'outro' (Delphi TIPOPAGTO não bate com enum MySQL confiável).
-- valor_baixa = VALOR Firebird (juros/desconto vão em colunas separadas).
-- ============================================================================

INSERT INTO fin_titulo_baixas (
    business_id,
    titulo_id,
    conta_bancaria_id,
    valor_baixa,
    juros,
    multa,
    desconto,
    data_baixa,
    meio_pagamento,
    idempotency_key,
    transaction_payment_id,
    estorno_de_id,
    observacoes,
    created_by,
    created_at
)
SELECT
    164                                                                       AS business_id,
    t.id                                                                      AS titulo_id,
    @conta_default                                                            AS conta_bancaria_id,
    COALESCE(s.valor, 0)                                                      AS valor_baixa,
    COALESCE(s.juros, 0)                                                      AS juros,
    0                                                                         AS multa,
    COALESCE(s.desconto, 0)                                                   AS desconto,
    s.data_pagto_parsed                                                       AS data_baixa,
    'outro'                                                                   AS meio_pagamento,
    LEFT(CONCAT('leg-164-', s.legacy_codigo), 36)                             AS idempotency_key,
    NULL                                                                      AS transaction_payment_id,
    NULL                                                                      AS estorno_de_id,
    CONCAT('Importado de FINANCEIRO Delphi CODIGO=', s.legacy_codigo)         AS observacoes,
    1                                                                         AS created_by,
    NOW()                                                                     AS created_at
FROM fin_titulos_staging_martinho s
JOIN fin_titulos t
  ON t.business_id    = 164
 AND t.origem         = 'manual'
 AND t.origem_id      = -CAST(s.legacy_codigo AS SIGNED)
 AND (t.parcela_numero IS NULL AND s.parcela_numero IS NULL
      OR t.parcela_numero = s.parcela_numero)
WHERE s.decision         = 'import'
  AND s.data_pagto_parsed IS NOT NULL
  AND COALESCE(s.valor, 0) > 0
ON DUPLICATE KEY UPDATE
    valor_baixa  = VALUES(valor_baixa),
    juros        = VALUES(juros),
    desconto     = VALUES(desconto),
    data_baixa   = VALUES(data_baixa);

-- ============================================================================
-- 4.3 — Resolução opcional de cliente_id (best-effort, sem bloquear)
-- ============================================================================
--
-- Estratégia: match DOCUMENTO (Firebird, normalizado) ↔ contacts.tax_number biz=164.
--   - contacts.legacy_id = CNPJ normalizado (Martinho v1404 pattern — ver migration
--     2026_05_13_170001_add_legacy_id_to_contacts.php)
--   - contacts.tax_number = mesmo CNPJ/CPF só-dígitos
--
-- Best-effort: títulos com DOCUMENTO ausente/inválido ficam com cliente_id=NULL
-- (cliente_descricao=RAZAOSOCIAL já é fallback adequado).
--
-- Não bloqueia o passo 5 — Q8 sample mostra quantos resolveram vs ficaram NULL.
-- ============================================================================

UPDATE fin_titulos t
JOIN fin_titulos_staging_martinho s
  ON s.legacy_codigo = JSON_UNQUOTE(JSON_EXTRACT(t.metadata, '$.legacy_id'))
JOIN contacts c
  ON c.business_id = 164
 AND c.tax_number  = REGEXP_REPLACE(s.documento, '[^0-9]', '')
SET t.cliente_id = c.id,
    t.updated_at = NOW()
WHERE t.business_id = 164
  AND t.origem      = 'manual'
  AND t.origem_id   < 0
  AND t.cliente_id IS NULL
  AND s.documento IS NOT NULL
  AND s.documento <> ''
  AND LENGTH(REGEXP_REPLACE(s.documento, '[^0-9]', '')) >= 11;  -- CPF=11, CNPJ=14

-- Cobertura do lookup:
SELECT
    SUM(CASE WHEN cliente_id IS NOT NULL THEN 1 ELSE 0 END) AS com_cliente_id,
    SUM(CASE WHEN cliente_id IS NULL     THEN 1 ELSE 0 END) AS sem_cliente_id,
    COUNT(*) AS total
FROM fin_titulos
WHERE business_id = 164 AND origem = 'manual' AND origem_id < 0;
-- EXPECTED: com_cliente_id > 50% se contacts Martinho está bem populado (9.988 rows).
--           sem_cliente_id alto = DOCUMENTO Firebird vazio (comum em A PAGAR genérico).

-- ============================================================================
-- Contagens finais pra diff
-- ============================================================================

SELECT
    (SELECT COUNT(*) FROM fin_titulos
        WHERE business_id = 164)                                              AS titulos_after,
    (SELECT COUNT(*) FROM fin_titulos
        WHERE business_id = 164 AND origem = 'manual' AND origem_id < 0)      AS titulos_legacy_after,
    (SELECT COUNT(*) FROM fin_titulo_baixas
        WHERE business_id = 164)                                              AS baixas_after,
    (SELECT COUNT(*) FROM fin_titulo_baixas
        WHERE business_id = 164 AND idempotency_key LIKE 'leg-164-%')         AS baixas_legacy_after;

-- ============================================================================
-- ROLLBACK (se precisar desfazer ESTE import — preserva pré-existente):
-- ============================================================================
--
-- DELETE FROM fin_titulo_baixas
-- WHERE business_id = 164
--   AND idempotency_key LIKE 'leg-164-%'
--   AND created_at >= '<timestamp do início do import>';
--
-- DELETE FROM fin_titulos
-- WHERE business_id = 164
--   AND origem = 'manual'
--   AND origem_id < 0
--   AND updated_at >= '<timestamp do início do import>';
--
-- DROP TABLE fin_titulos_staging_martinho;
-- ============================================================================
