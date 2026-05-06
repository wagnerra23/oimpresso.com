-- ============================================================
-- Sprint 2 — Índices de listagem em transactions (sub_type=repair)
-- ============================================================
-- Objetivo: garantir p95 < 400ms na listagem com filtros combinados.
-- Banco: MySQL 8.0 / MariaDB 10.6 (oimpresso usa MySQL 8 em prod).
-- Idempotente: roda múltiplas vezes sem erro.
-- Tabela alvo: `transactions` (UltimatePOS core).
-- Filtro de domínio: type='sell' AND sub_type='repair'.
-- ============================================================

-- Contexto: a migration `2021_02_16_190423_add_repair_module_indexing.php`
-- já criou índices SINGLE em repair_warranty_id, repair_brand_id,
-- repair_status_id, repair_device_id, repair_job_sheet_id.
--
-- Faltam índices COMPOSTOS pra listagem com filtros combinados, sem
-- penalizar queries não-Repair (sell/purchase normais).
--
-- Toda condição inclui `business_id` na ordem mais à esquerda
-- (multi-tenant first). MySQL escolhe esses sobre os SINGLE existentes
-- quando o WHERE bate.

-- 1) Listagem padrão: business + sub_type=repair + status + due_date
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND index_name = 'idx_repair_biz_status_due'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_repair_biz_status_due ON transactions (business_id, sub_type, repair_status_id, repair_due_date)',
    'SELECT "idx_repair_biz_status_due já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Filtro por cliente: business + sub_type=repair + contact_id
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND index_name = 'idx_repair_biz_contact_created'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_repair_biz_contact_created ON transactions (business_id, sub_type, contact_id, created_at)',
    'SELECT "idx_repair_biz_contact_created já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Filtro "minhas OS" (responsável = service staff)
--    res_waiter_id é o campo usado em RepairController para serviceStaff
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND index_name = 'idx_repair_biz_waiter_status'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_repair_biz_waiter_status ON transactions (business_id, sub_type, res_waiter_id, repair_status_id)',
    'SELECT "idx_repair_biz_waiter_status já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Filtro "minhas OS criadas" (created_by) — usado pra repair.view_own
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND index_name = 'idx_repair_biz_creator_status'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_repair_biz_creator_status ON transactions (business_id, sub_type, created_by, repair_status_id)',
    'SELECT "idx_repair_biz_creator_status já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Filtro por location (multi-loja) — comum em ROTA LIVRE
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'transactions'
      AND index_name = 'idx_repair_biz_location_status'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_repair_biz_location_status ON transactions (business_id, sub_type, location_id, repair_status_id)',
    'SELECT "idx_repair_biz_location_status já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- FULLTEXT é OPCIONAL — avaliar antes de aplicar
-- ============================================================
-- transactions já é uma tabela quente do UltimatePOS (sells, purchases,
-- returns). FULLTEXT em colunas Repair pode ter custo de manutenção
-- alto pra ganho marginal (90% das buscas são por invoice_no exato).
--
-- Recomendação: NÃO criar FULLTEXT na Sprint 2. Usar LIKE em invoice_no
-- (já indexado) e REGEXP em repair_serial_no se necessário. Reavaliar
-- na Sprint 3 com EXPLAIN ANALYZE de queries reais.

-- Se Wagner decidir aplicar mesmo assim:
-- ALTER TABLE transactions
--     ADD FULLTEXT INDEX ft_repair_defects_notes (repair_defects, additional_notes);
-- (testar primeiro em staging com volume real ROTA LIVRE)

-- ============================================================
-- Validação pós-deploy
-- ============================================================
-- Wagner: rodar em staging APÓS migrate, colar resultado no checklist.

-- 1) Listar todos os índices Repair-related
-- SELECT
--     index_name,
--     GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols,
--     index_type,
--     non_unique
-- FROM information_schema.statistics
-- WHERE table_schema = DATABASE()
--   AND table_name = 'transactions'
--   AND (index_name LIKE 'idx_repair%' OR column_name LIKE 'repair_%')
-- GROUP BY index_name, index_type, non_unique
-- ORDER BY index_name;

-- 2) Validar plano de execução da query principal
-- EXPLAIN ANALYZE
-- SELECT t.id, t.invoice_no, t.contact_id, t.repair_status_id, t.repair_due_date, t.final_total
-- FROM transactions t
-- WHERE t.business_id = 4              -- ROTA LIVRE
--   AND t.type = 'sell'
--   AND t.sub_type = 'repair'
--   AND t.repair_status_id IN (1, 2, 3)
-- ORDER BY t.repair_due_date ASC
-- LIMIT 50;
-- -- Esperado: "Index Lookup" em idx_repair_biz_status_due, < 50ms

-- 3) Tamanho dos novos índices (sanity check)
-- SELECT
--     index_name,
--     ROUND(stat_value * @@innodb_page_size / 1024 / 1024, 2) AS size_mb
-- FROM mysql.innodb_index_stats
-- WHERE database_name = DATABASE()
--   AND table_name = 'transactions'
--   AND stat_name = 'size'
--   AND index_name LIKE 'idx_repair%';
-- -- Esperado em prod oimpresso (volume atual): < 50 MB total

-- ============================================================
-- Migration Laravel correspondente (criar em PR3)
-- ============================================================
-- Em vez de aplicar este SQL direto, virar migration Laravel:
-- Modules/Repair/Database/Migrations/2026_05_XX_add_repair_listing_indexes.php
-- com $table->index([...], 'idx_nome_curto') idempotente via has_index check.
-- Este .sql é o canônico — a migration deve produzir os MESMOS nomes de índice.
