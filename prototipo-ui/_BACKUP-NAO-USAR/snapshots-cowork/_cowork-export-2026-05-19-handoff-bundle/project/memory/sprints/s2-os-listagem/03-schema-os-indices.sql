-- ============================================================
-- Sprint 2 — Índices de listagem em ordens_servico
-- ============================================================
-- Objetivo: garantir p95 < 400ms na listagem com filtros combinados
-- Banco: MySQL 8.0 / MariaDB 10.6
-- Idempotente: roda múltiplas vezes sem erro
-- ============================================================

-- 1) Índice composto pra listagem padrão (empresa + status + prazo)
--    Usado em: Os/Index sem filtros adicionais
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ordens_servico'
      AND index_name = 'idx_os_empresa_status_prazo'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_os_empresa_status_prazo ON ordens_servico (empresa_id, status, prazo_entrega DESC)',
    'SELECT "idx_os_empresa_status_prazo já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Índice por cliente (filtro frequente)
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ordens_servico'
      AND index_name = 'idx_os_empresa_cliente'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_os_empresa_cliente ON ordens_servico (empresa_id, cliente_id, created_at DESC)',
    'SELECT "idx_os_empresa_cliente já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Índice por responsável (filtro "minhas OS")
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ordens_servico'
      AND index_name = 'idx_os_responsavel_status'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_os_responsavel_status ON ordens_servico (responsavel_id, status, prazo_entrega ASC)',
    'SELECT "idx_os_responsavel_status já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Índice para busca por número/código (search box)
--    OBS: número da OS é VARCHAR(20) tipo "OS-2026-04321"
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ordens_servico'
      AND index_name = 'idx_os_numero'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_os_numero ON ordens_servico (empresa_id, numero)',
    'SELECT "idx_os_numero já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) FULLTEXT em descricao + observacoes (search box "buscar texto livre")
--    Só em MySQL 8 (InnoDB FULLTEXT). Skip se já existir.
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'ordens_servico'
      AND index_name = 'ft_os_descricao_obs'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE FULLTEXT INDEX ft_os_descricao_obs ON ordens_servico (descricao, observacoes)',
    'SELECT "ft_os_descricao_obs já existe" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- Colunas que podem estar faltando (verificar antes de aplicar)
-- ============================================================
-- Estas vêm do form Nova OS do protótipo. Comentadas pra Wagner
-- decidir se já existem no banco real.

-- ALTER TABLE ordens_servico
--     ADD COLUMN IF NOT EXISTS prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media' AFTER status,
--     ADD COLUMN IF NOT EXISTS tags JSON NULL AFTER observacoes,
--     ADD COLUMN IF NOT EXISTS arquivada_em TIMESTAMP NULL AFTER updated_at,
--     ADD INDEX idx_os_prioridade (empresa_id, prioridade, prazo_entrega);

-- ============================================================
-- Validação pós-deploy
-- ============================================================
-- Wagner: rodar isso depois do migrate e colar o resultado no checklist.

-- SELECT
--     index_name,
--     GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols,
--     index_type
-- FROM information_schema.statistics
-- WHERE table_schema = DATABASE()
--   AND table_name = 'ordens_servico'
-- GROUP BY index_name, index_type
-- ORDER BY index_name;

-- EXPLAIN ANALYZE
-- SELECT id, numero, cliente_id, status, prazo_entrega
-- FROM ordens_servico
-- WHERE empresa_id = 1
--   AND status IN ('briefing','arte','aprovacao','producao')
-- ORDER BY prazo_entrega ASC
-- LIMIT 50;
-- -- Esperado: Index Lookup em idx_os_empresa_status_prazo, < 50ms
