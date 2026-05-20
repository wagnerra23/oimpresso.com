-- ============================================================================
-- 01 — EXPORT CLIENTES Firebird → CSV (rodar no DBeaver/isql no FB do cliente)
-- ============================================================================
--
-- Pré-requisito: DBeaver conectado ao Firebird local do cliente com fbclient.dll.
-- Equivalente Python: scripts/firebird/export-customers.py
--
-- Output: resultset → "Export resultset" no DBeaver → CSV
--   - Encoding: UTF-8
--   - Field separator: ,
--   - Quote char: "
--   - Header: true
--
-- Tabela alvo Firebird: CLIENTES (subset cliente-only de PESSOAS)
-- Filtro: BLOQUEADO != 'S' opcional (deixa comentado pra trazer inclusive bloqueados;
--         marcamos contact_status='inactive' no passo 4)
--
-- 329 cols PESSOAS originais → 10 colunas canônicas exportadas.
-- Campos perdidos intencionalmente (PLACA/MARCAMODELO/ANO etc) — não pertencem a contacts.
-- ============================================================================

SELECT
    CODIGO            AS legacy_codigo,        -- PK Firebird (vai pra contacts.legacy_id)
    RAZAO_SOCIAL      AS razao_social,         -- contacts.name + .supplier_business_name
    COALESCE(CNPJ, CPF) AS cpf_cnpj,           -- contacts.tax_number (CNPJ tem precedência)
    FONE1             AS telefone_principal,   -- contacts.mobile
    FONE2             AS telefone_alternativo, -- contacts.alternate_number
    LOWER(EMAIL)      AS email,                -- contacts.email
    CIDADE            AS cidade,               -- contacts.city
    BLOQUEADO         AS bloqueado_sn,         -- mapeia pra contact_status (S=inactive, N=active)
    DATACADASTRO      AS data_cadastro         -- contacts.created_at
    -- Opcionais (descomentar se cliente usa):
    -- , ENDERECO    AS endereco
    -- , NUMERO      AS numero
    -- , BAIRRO      AS bairro
    -- , UF          AS estado
    -- , CEP         AS cep
    -- , OBSERVACOES AS observacoes
FROM CLIENTES
WHERE 1=1
  -- AND BLOQUEADO != 'S'                       -- descomenta pra excluir bloqueados
  -- AND DATACADASTRO >= '2020-01-01'           -- descomenta pra filtrar período
  -- AND UPPER(RAZAO_SOCIAL) LIKE UPPER('%XYZ%') -- descomenta pra cliente específico
ORDER BY CODIGO;

-- ============================================================================
-- VALIDAÇÕES PRÉ-EXPORT (rodar antes de exportar pra dimensionar):
-- ============================================================================
--
-- Contagem total:
--   SELECT COUNT(*) FROM CLIENTES;
--
-- Quantos têm CNPJ vs CPF:
--   SELECT
--     SUM(CASE WHEN CNPJ IS NOT NULL AND CNPJ <> '' THEN 1 ELSE 0 END) AS com_cnpj,
--     SUM(CASE WHEN CPF  IS NOT NULL AND CPF  <> '' THEN 1 ELSE 0 END) AS com_cpf,
--     SUM(CASE WHEN COALESCE(CNPJ,'')='' AND COALESCE(CPF,'')='' THEN 1 ELSE 0 END) AS sem_doc
--   FROM CLIENTES;
--
-- Bloqueados:
--   SELECT COUNT(*) FROM CLIENTES WHERE BLOQUEADO = 'S';
--
-- Duplicatas potenciais por CNPJ/CPF (PRINCIPAL armadilha — Delphi não tinha UNIQUE):
--   SELECT COALESCE(CNPJ,CPF) AS doc, COUNT(*) AS cnt
--   FROM CLIENTES
--   WHERE COALESCE(CNPJ, CPF, '') <> ''
--   GROUP BY COALESCE(CNPJ,CPF)
--   HAVING COUNT(*) > 1
--   ORDER BY cnt DESC;
--   -- Wagner decide: mantém todos OU consolida no de CODIGO menor.
--
-- ============================================================================
