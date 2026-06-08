-- ============================================================================
-- 05 — VALIDAÇÃO pós-import (rodar no MySQL prod APÓS passo 04)
-- ============================================================================
--
-- 🚨 biz=164 (Martinho) hard-coded.
--
-- 8 queries de validação. Rodar todas. Qualquer FAIL = investigar antes de
-- considerar migração concluída.
-- ============================================================================

-- ── Q1: Diff staging vs fin_titulos importados ──────────────────────────────
SELECT
    'staging_para_importar'    AS source, COUNT(*) AS rows_count
        FROM fin_titulos_staging_martinho WHERE decision = 'import'
UNION ALL
SELECT
    'staging_skip'             AS source, COUNT(*)
        FROM fin_titulos_staging_martinho WHERE decision = 'skip'
UNION ALL
SELECT
    'titulos_legacy_importados' AS source, COUNT(*)
        FROM fin_titulos
        WHERE business_id = 164 AND origem = 'manual' AND origem_id < 0;
-- EXPECTED: titulos_legacy_importados >= staging_para_importar (>= por reruns prévios).

-- ── Q2: Distribuição tipo (sanity check A RECEBER vs A PAGAR) ───────────────
SELECT
    tipo,
    COUNT(*)       AS qtd,
    SUM(valor_total) AS valor_total_acum
FROM fin_titulos
WHERE business_id = 164
  AND origem = 'manual'
  AND origem_id < 0
GROUP BY tipo
ORDER BY 2 DESC;
-- EXPECTED: 'receber' >> 'pagar' (Martinho gráfica/caçambas → mais a receber).
--           Confronta com mesmo SELECT no Firebird (passo 01 §validações).

-- ── Q3: Distribuição status (aberto/quitado/cancelado) ──────────────────────
SELECT
    status,
    COUNT(*)       AS qtd,
    SUM(valor_total)  AS valor_total_acum,
    SUM(valor_aberto) AS valor_aberto_acum
FROM fin_titulos
WHERE business_id = 164
  AND origem = 'manual'
  AND origem_id < 0
GROUP BY status
ORDER BY 2 DESC;
-- EXPECTED:
--   status='quitado'   → valor_aberto deve ser 0 (invariante)
--   status='cancelado' → valor_aberto deve ser 0
--   status='aberto'    → valor_aberto = valor_total

-- ── Q4: Invariante valor_aberto consistente ────────────────────────────────
SELECT COUNT(*) AS rows_violando_invariante_aberto
FROM fin_titulos
WHERE business_id = 164
  AND origem = 'manual'
  AND origem_id < 0
  AND (
      (status = 'aberto'    AND valor_aberto <> valor_total) OR
      (status = 'quitado'   AND valor_aberto <> 0) OR
      (status = 'cancelado' AND valor_aberto <> 0)
  );
-- EXPECTED: 0. >0 = bug no passo 4.

-- ── Q5: Write-off candidates (Wagner aging bombshell) ───────────────────────
SELECT
    JSON_EXTRACT(metadata, '$.is_write_off_candidate')  AS is_write_off,
    COUNT(*)                                            AS qtd,
    SUM(valor_total)                                    AS valor_total_acum
FROM fin_titulos
WHERE business_id = 164
  AND origem = 'manual'
  AND origem_id < 0
GROUP BY 1;
-- EXPECTED com filtro EMISSAO>=2020-01-01 no passo 01:
--   write_off=true: <5% volume, valor pequeno (~R$ [redacted Tier 0]k vs R$ [redacted Tier 0]M sem filtro).
--   Se >20% volume → revisar filtro temporal.

-- ── Q6: Baixas linkadas vs órfãs ────────────────────────────────────────────
SELECT
    'baixas_total'    AS metric, COUNT(*) AS qtd
        FROM fin_titulo_baixas
        WHERE business_id = 164 AND idempotency_key LIKE 'leg-164-%'
UNION ALL
SELECT
    'baixas_orfas'    AS metric, COUNT(*)
        FROM fin_titulo_baixas b
        LEFT JOIN fin_titulos t ON t.id = b.titulo_id
        WHERE b.business_id = 164
          AND b.idempotency_key LIKE 'leg-164-%'
          AND t.id IS NULL
UNION ALL
SELECT
    'titulos_quitado_sem_baixa' AS metric, COUNT(*)
        FROM fin_titulos t
        LEFT JOIN fin_titulo_baixas b
          ON b.business_id = 164
         AND b.titulo_id = t.id
         AND b.idempotency_key LIKE 'leg-164-%'
        WHERE t.business_id = 164
          AND t.origem = 'manual'
          AND t.origem_id < 0
          AND t.status = 'quitado'
          AND b.id IS NULL;
-- EXPECTED:
--   baixas_orfas = 0 (FK garante, mas double-check)
--   titulos_quitado_sem_baixa = 0 (todo quitado deve ter ao menos 1 baixa).
--   Se >0 quitado_sem_baixa: investigar passo 4.2 (provavelmente VALOR=0 ou data_pagto vazia).

-- ── Q7: Cross-tenant check — Tier 0 paranoia (ADR 0093) ─────────────────────
SELECT
    business_id,
    COUNT(*) AS rows_com_legacy_neg
FROM fin_titulos
WHERE origem = 'manual'
  AND origem_id < 0
  AND origem_id IN (
      SELECT -CAST(legacy_codigo AS SIGNED) FROM fin_titulos_staging_martinho LIMIT 100
  )
GROUP BY business_id;
-- EXPECTED: só business_id=164. QUALQUER outro = LEAK CROSS-TENANT.
--           ⚠️ PARE TUDO e investigar. Não rode próximos imports antes de corrigir.

-- ── Q8: Sample 10 mais recentes pra eyeball ─────────────────────────────────
SELECT
    t.id,
    t.business_id,
    t.numero,
    t.tipo,
    t.status,
    t.valor_total,
    t.valor_aberto,
    t.emissao,
    t.vencimento,
    JSON_EXTRACT(t.metadata, '$.legacy_id')         AS legacy_id,
    JSON_EXTRACT(t.metadata, '$.delphi_status_raw') AS delphi_status,
    (SELECT COUNT(*) FROM fin_titulo_baixas b WHERE b.titulo_id = t.id) AS qtd_baixas
FROM fin_titulos t
WHERE t.business_id = 164
  AND t.origem = 'manual'
  AND t.origem_id < 0
ORDER BY t.id DESC
LIMIT 10;

-- ============================================================================
-- BONUS: Diff agregado Firebird ↔ MySQL (rodar valores no FB e comparar)
-- ============================================================================
--
-- No Firebird (passo 01):
--   SELECT
--     TIPO,
--     CASE WHEN DATAPAGTO IS NULL THEN 'aberto' ELSE 'quitado' END AS status_fb,
--     COUNT(*) AS qtd,
--     SUM(VALOR) AS valor_total
--   FROM FINANCEIRO
--   WHERE EMISSAO >= '2020-01-01'
--     AND COALESCE(ATIVO, 'S') <> 'N'
--     AND COALESCE(STATUS, 'ATIVO') NOT IN (
--         'INATIVO AGRUPADO','INATIVO EXCLUIDO','INATIVO EXCLUIDA',
--         'INATIVO EXCULIDO','INATIVO EXC.AGRUPADO',
--         'INATIVO PREVISÃO','INATIVO PREVISAO'
--     )
--   GROUP BY TIPO, CASE WHEN DATAPAGTO IS NULL THEN 'aberto' ELSE 'quitado' END;
--
-- No MySQL (depois deste passo):
--   SELECT tipo, status, COUNT(*) AS qtd, SUM(valor_total) AS valor_total
--   FROM fin_titulos
--   WHERE business_id = 164 AND origem = 'manual' AND origem_id < 0
--   GROUP BY tipo, status;
--
-- Drift aceitável < 1% (skips de valor_zero + sem_emissao).
-- Drift > 5% = investigar (provavelmente classify_status divergente ou parse de data).
-- ============================================================================

-- ============================================================================
-- Se TUDO passou, cleanup staging:
--   DROP TABLE fin_titulos_staging_martinho;
--
-- E gravar snapshot canônico:
--   memory/sessions/YYYY-MM-DD-migracao-financeiro-martinho-biz164.md
--   com contagem antes/depois + drift Firebird-MySQL + decisões.
-- ============================================================================
