-- ============================================================================
-- 01 — EXPORT FINANCEIRO Firebird → CSV (rodar no DBeaver no Firebird Martinho)
-- ============================================================================
--
-- Pré-requisito: DBeaver conectado ao Firebird `MartinhoServidor` (alias Wagner).
-- Equivalente Python: scripts/legacy-migration/import-financeiro.py
--
-- Output: resultset → "Export resultset" no DBeaver → CSV
--   - Encoding: UTF-8
--   - Field separator: ,
--   - Quote char: "
--   - Quote all: false (só strings)
--   - Header: true
--   - NULL string: (vazio)
--
-- Salvar em path acessível ao MySQL:
--   D:\export\financeiro-martinho-2026-MM-DD.csv
--   /home/admin/csv/financeiro-martinho-2026-MM-DD.csv
--
-- Tabela alvo Firebird: FINANCEIRO (57 cols originais → 24 canônicas exportadas).
-- Filtros aplicados:
--   - EMISSAO >= 2020-01-01 (exclui R$ [redacted Tier 0]M fóssil pré-2020 — handoff 2026-05-17)
--   - STATUS NOT IN (lixo) (skip INATIVO AGRUPADO/EXCLUIDO/PREVISÃO)
--   - ATIVO != 'N' (skip soft-delete Delphi)
--
-- ============================================================================

SELECT
    -- Identidade
    CODIGO                       AS legacy_codigo,           -- PK Firebird → numero=LEG-{CODIGO}
    CODPEDIDO                    AS cod_pedido,              -- lookup transactions.ref_no
    CODEMPRESA                   AS cod_empresa,             -- audit: deve ser 1 (Martinho single-tenant FB)
    PESSOA_RESPONSAVEL_CODIGO    AS pessoa_responsavel,      -- TODO: futuro lookup contacts.legacy_id

    -- PII (vai pra metadata redacted no upsert)
    RAZAOSOCIAL                  AS razao_social,            -- → cliente_descricao
    DOCUMENTO                    AS documento,               -- → metadata.delphi_documento_redacted
    NOTAFISCAL                   AS nota_fiscal,             -- → metadata.delphi_notafiscal_redacted
    BOLETO_NOSSO_NR              AS boleto_nosso_nr,         -- → metadata.delphi_boleto_redacted + flag write-off

    -- Histórico / observações
    HISTORICO                    AS historico,               -- → observacoes (max 500)

    -- Datas
    EMISSAO                      AS emissao,                 -- → fin_titulos.emissao
    VENCTO                       AS vencto,                  -- → fin_titulos.vencimento
    DATAPAGTO                    AS data_pagto,              -- NULL = aberto · NOT NULL = quitado + baixa
    DT_COMPETENCIA               AS dt_competencia,          -- → competencia_mes (fallback VENCTO)

    -- Valores
    VALOR                        AS valor,                   -- → valor_total
    JUROS                        AS juros,                   -- → fin_titulo_baixas.juros (se baixa) + metadata
    DESCONTO                     AS desconto,                -- → fin_titulo_baixas.desconto (se baixa) + metadata

    -- Plano contábil / categoria
    CODPLANOCONTAS               AS cod_plano_contas,        -- lookup fin_planos_conta.legacy_id (NULL se não mapeado)
    CODCONTA                     AS cod_conta,               -- lookup fin_contas_bancarias.legacy_id (fallback default)

    -- Pagamento
    TIPOPAGTO                    AS tipo_pagto,              -- → metadata · meio_pagamento sempre 'outro' (Delphi não distingue confiável)
    CONDICAOPAGTO                AS condicao_pagto,          -- → metadata
    PARCELA                      AS parcela,                 -- '1/3' → parcela_numero=1, parcela_total=3

    -- Classificação Delphi
    TIPO                         AS tipo,                    -- 'A RECEBER'/'RECEBIDA' → 'receber' · 'A PAGAR'/'PAGA' → 'pagar'
    STATUS                       AS status_delphi,           -- gate classify_status (ver lista abaixo)
    ATIVO                        AS ativo_sn                 -- 'S'/'N' (filtra N no WHERE)

FROM FINANCEIRO
WHERE EMISSAO >= '2020-01-01'
  AND COALESCE(ATIVO, 'S') <> 'N'
  AND COALESCE(STATUS, 'ATIVO') NOT IN (
      'INATIVO AGRUPADO',
      'INATIVO EXCLUIDO',
      'INATIVO EXCLUIDA',
      'INATIVO EXCULIDO',     -- typo Delphi histórico, real
      'INATIVO EXC.AGRUPADO',
      'INATIVO PREVISÃO',
      'INATIVO PREVISAO'
  )
ORDER BY CODIGO;

-- ============================================================================
-- VALIDAÇÕES PRÉ-EXPORT (rodar antes de exportar pra dimensionar):
-- ============================================================================
--
-- Contagem total (todo o FINANCEIRO Martinho):
--   SELECT COUNT(*) FROM FINANCEIRO;
--   -- esperado: ~83k+ (alinhado com 83.107 fin_titulos prod biz=164 handoff 2026-05-17)
--
-- Contagem após filtros (deve casar com staging_rows do passo 02):
--   SELECT COUNT(*) FROM FINANCEIRO
--   WHERE EMISSAO >= '2020-01-01'
--     AND COALESCE(ATIVO, 'S') <> 'N'
--     AND COALESCE(STATUS, 'ATIVO') NOT IN (
--         'INATIVO AGRUPADO','INATIVO EXCLUIDO','INATIVO EXCLUIDA',
--         'INATIVO EXCULIDO','INATIVO EXC.AGRUPADO',
--         'INATIVO PREVISÃO','INATIVO PREVISAO'
--     );
--
-- Distribuição TIPO (sanity check A RECEBER vs A PAGAR):
--   SELECT TIPO, COUNT(*) FROM FINANCEIRO
--   WHERE EMISSAO >= '2020-01-01' GROUP BY TIPO ORDER BY 2 DESC;
--
-- Distribuição STATUS (entender o que vai entrar):
--   SELECT STATUS, COUNT(*) FROM FINANCEIRO
--   WHERE EMISSAO >= '2020-01-01' GROUP BY STATUS ORDER BY 2 DESC;
--
-- Aberto vs quitado:
--   SELECT
--     SUM(CASE WHEN DATAPAGTO IS NULL THEN 1 ELSE 0 END) AS abertos,
--     SUM(CASE WHEN DATAPAGTO IS NOT NULL THEN 1 ELSE 0 END) AS quitados
--   FROM FINANCEIRO WHERE EMISSAO >= '2020-01-01';
--
-- Write-off candidates (>365d vencido sem boleto sem mov — Wagner aging bombshell):
--   SELECT COUNT(*) FROM FINANCEIRO
--   WHERE TIPO LIKE '%RECEBER%'
--     AND VENCTO < (CURRENT_DATE - 365)
--     AND DATAPAGTO IS NULL
--     AND COALESCE(BOLETO_NOSSO_NR, '') = ''
--     AND COALESCE(JUROS, 0) = 0
--     AND COALESCE(DESCONTO, 0) = 0
--     AND EMISSAO >= '2020-01-01';
--   -- Esperado próximo a 0 com filtro 2020+ (R$ [redacted Tier 0]M era pré-2020).
--
-- Duplicatas CODIGO (não deveria ter, PK Firebird):
--   SELECT CODIGO, COUNT(*) FROM FINANCEIRO GROUP BY CODIGO HAVING COUNT(*) > 1;
--
-- ============================================================================
-- ⚠️ Multi-tenant note (ADR 0093):
--    Firebird Martinho é single-tenant (DB inteiro = só ele). CODEMPRESA quase
--    sempre = 1. Se aparecer múltiplos CODEMPRESA, INVESTIGAR antes de exportar
--    — pode ser DB consolidado de múltiplos clientes (raro mas possível).
--
--    Query de check:
--      SELECT CODEMPRESA, COUNT(*) FROM FINANCEIRO GROUP BY CODEMPRESA;
--    Esperado: 1 row só.
-- ============================================================================
