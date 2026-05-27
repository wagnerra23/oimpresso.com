-- ============================================================================
-- 03 — LOAD CSV → staging + normaliza (rodar no MySQL prod após passo 02)
-- ============================================================================
--
-- ⚠️ Substitua ${CSV_PATH} pelo caminho ABSOLUTO do CSV exportado no passo 01.
--    Ex (Linux):   /home/admin/csv/financeiro-martinho-2026-05-20.csv
--    Ex (Windows): D:/export/financeiro-martinho-2026-05-20.csv
--
-- Pré-requisito MySQL: local_infile=ON.
--   SHOW VARIABLES LIKE 'local_infile';
--   SET GLOBAL local_infile = 1;  -- volátil; pra persistir, editar my.cnf
--
-- Cliente MySQL precisa flag --local-infile=1:
--   mysql --local-infile=1 -h prod -u admin -p oimpresso
--
-- Após LOAD: 4 UPDATEs de parsing/normalização + classify_status final.
-- ============================================================================

LOAD DATA LOCAL INFILE '${CSV_PATH}'
INTO TABLE fin_titulos_staging_martinho
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES  -- pula header
(
    legacy_codigo, cod_pedido, cod_empresa, pessoa_responsavel,
    razao_social, documento, nota_fiscal, boleto_nosso_nr,
    historico,
    emissao, vencto, data_pagto, dt_competencia,
    @valor_raw, @juros_raw, @desconto_raw,
    cod_plano_contas, cod_conta,
    tipo_pagto, condicao_pagto, parcela,
    tipo, status_delphi, ativo_sn
)
SET
    valor    = CAST(NULLIF(@valor_raw,    '') AS DECIMAL(22,4)),
    juros    = CAST(COALESCE(NULLIF(@juros_raw,    ''), '0') AS DECIMAL(22,4)),
    desconto = CAST(COALESCE(NULLIF(@desconto_raw, ''), '0') AS DECIMAL(22,4));

-- ── 3.1 Parse datas (Firebird traz como TIMESTAMP / DATE em vários formatos) ─

UPDATE fin_titulos_staging_martinho
SET emissao_parsed = STR_TO_DATE(SUBSTRING(emissao, 1, 10), '%Y-%m-%d')
WHERE emissao IS NOT NULL AND emissao <> '' AND emissao_parsed IS NULL;

UPDATE fin_titulos_staging_martinho
SET vencto_parsed = STR_TO_DATE(SUBSTRING(vencto, 1, 10), '%Y-%m-%d')
WHERE vencto IS NOT NULL AND vencto <> '' AND vencto_parsed IS NULL;

UPDATE fin_titulos_staging_martinho
SET data_pagto_parsed = STR_TO_DATE(SUBSTRING(data_pagto, 1, 10), '%Y-%m-%d')
WHERE data_pagto IS NOT NULL AND data_pagto <> '' AND data_pagto_parsed IS NULL;

-- Fallback: se vencto NULL, herda emissão
UPDATE fin_titulos_staging_martinho
SET vencto_parsed = emissao_parsed
WHERE vencto_parsed IS NULL AND emissao_parsed IS NOT NULL;

-- Fallback inverso (raríssimo): emissao NULL com vencto presente
UPDATE fin_titulos_staging_martinho
SET emissao_parsed = vencto_parsed
WHERE emissao_parsed IS NULL AND vencto_parsed IS NOT NULL;

-- ── 3.2 competencia_mes (YYYY-MM) — DT_COMPETENCIA ou fallback VENCTO ────────

UPDATE fin_titulos_staging_martinho
SET competencia_mes = DATE_FORMAT(STR_TO_DATE(SUBSTRING(dt_competencia, 1, 10), '%Y-%m-%d'), '%Y-%m')
WHERE dt_competencia IS NOT NULL AND dt_competencia <> '';

UPDATE fin_titulos_staging_martinho
SET competencia_mes = DATE_FORMAT(vencto_parsed, '%Y-%m')
WHERE competencia_mes IS NULL AND vencto_parsed IS NOT NULL;

-- Fallback final defensivo
UPDATE fin_titulos_staging_martinho
SET competencia_mes = '1900-01'
WHERE competencia_mes IS NULL;

-- ── 3.3 tipo_normalized ('receber'/'pagar') ─────────────────────────────────

UPDATE fin_titulos_staging_martinho
SET tipo_normalized =
    CASE
        WHEN UPPER(IFNULL(tipo,'')) LIKE '%RECEBER%' OR UPPER(IFNULL(tipo,'')) LIKE 'RECEBIDA%' THEN 'receber'
        ELSE 'pagar'
    END;

-- ── 3.4 status_normalized (aberto/quitado/cancelado) ────────────────────────

UPDATE fin_titulos_staging_martinho
SET status_normalized =
    CASE
        WHEN data_pagto_parsed IS NOT NULL                                                   THEN 'quitado'
        WHEN UPPER(IFNULL(status_delphi,'')) = 'INATIVO CANCELADA'                           THEN 'cancelado'
        ELSE                                                                                       'aberto'
    END;

-- ── 3.5 parcela_numero / parcela_total ('1/3' → 1, 3) ───────────────────────

UPDATE fin_titulos_staging_martinho
SET
    parcela_numero = CAST(SUBSTRING_INDEX(parcela, '/', 1) AS UNSIGNED),
    parcela_total  = CAST(SUBSTRING_INDEX(parcela, '/', -1) AS UNSIGNED)
WHERE parcela LIKE '%/%';

UPDATE fin_titulos_staging_martinho
SET parcela_numero = CAST(parcela AS UNSIGNED)
WHERE parcela IS NOT NULL AND parcela NOT LIKE '%/%'
  AND parcela REGEXP '^[0-9]+$';

-- Limita range (TINYINT UNSIGNED = 0-255)
UPDATE fin_titulos_staging_martinho
SET parcela_numero = NULL WHERE parcela_numero > 255;

UPDATE fin_titulos_staging_martinho
SET parcela_total  = NULL WHERE parcela_total  > 255 OR parcela_total = 0;

-- ── 3.6 is_write_off_candidate (aging bombshell heuristic Wagner) ───────────

UPDATE fin_titulos_staging_martinho
SET is_write_off_candidate = 1
WHERE tipo_normalized = 'receber'
  AND data_pagto_parsed IS NULL
  AND vencto_parsed < DATE_SUB(CURDATE(), INTERVAL 365 DAY)
  AND COALESCE(boleto_nosso_nr, '') = ''
  AND COALESCE(juros, 0) = 0
  AND COALESCE(desconto, 0) = 0;

-- ── 3.7 decision/skip_reason (espelha STATUS_FILTERS do import-financeiro.py) ─

UPDATE fin_titulos_staging_martinho
SET decision = 'skip', skip_reason = 'valor_zero'
WHERE COALESCE(valor, 0) = 0;

UPDATE fin_titulos_staging_martinho
SET decision = 'skip', skip_reason = 'sem_emissao'
WHERE emissao_parsed IS NULL AND decision = 'import';

-- ============================================================================
-- VALIDAÇÕES PÓS-LOAD (rodar antes de seguir pro passo 04):
-- ============================================================================
--
-- Total carregado:
--   SELECT COUNT(*) FROM fin_titulos_staging_martinho;
--
-- Distribuição decision/skip_reason:
--   SELECT decision, skip_reason, COUNT(*) FROM fin_titulos_staging_martinho
--   GROUP BY decision, skip_reason ORDER BY 3 DESC;
--
-- Distribuição tipo + status (sanity):
--   SELECT tipo_normalized, status_normalized, COUNT(*)
--   FROM fin_titulos_staging_martinho
--   GROUP BY tipo_normalized, status_normalized;
--
-- Write-off candidates count:
--   SELECT COUNT(*) FROM fin_titulos_staging_martinho WHERE is_write_off_candidate = 1;
--
-- Datas inválidas (não deveria ter):
--   SELECT COUNT(*) FROM fin_titulos_staging_martinho WHERE emissao_parsed IS NULL;
--
-- Duplicatas legacy_codigo (não deveria ter — UK do staging):
--   SELECT legacy_codigo, COUNT(*) FROM fin_titulos_staging_martinho
--   GROUP BY legacy_codigo HAVING COUNT(*) > 1;
--
-- Sample 10 mais recentes pra eyeball:
--   SELECT legacy_codigo, tipo_normalized, status_normalized, valor,
--          emissao_parsed, vencto_parsed, data_pagto_parsed,
--          is_write_off_candidate, decision, skip_reason
--   FROM fin_titulos_staging_martinho
--   ORDER BY raw_csv_line DESC LIMIT 10;
-- ============================================================================
