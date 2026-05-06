-- =====================================================================
-- Sprint 1 — Daily Brief
-- Schema: tabela de briefs gerados + tabela cache agregadora
-- =====================================================================
-- Stack alvo: MySQL 8.0+ (Hostinger u906587222_oimpresso, ADR 0053).
-- Aplicar via: php artisan make:migration create_daily_brief_schema
--              + colar este conteúdo dentro de up()/down()
--              ou rodar direto via SSH tunnel pro Hostinger.
--
-- NOTAS:
-- - Postgres MATERIALIZED VIEW CONCURRENTLY não existe em MySQL.
--   Substituído por tabela cache singleton `mcp_brief_inputs_cache`
--   atualizada por TRUNCATE+INSERT pelo command brief:generate
--   imediatamente antes da chamada Brain B.
-- - Nomenclatura mcp_* segue ADR 0070 (Jira-style, prefixo único).
-- - Seções dependentes de tabelas futuras (mcp_design_locks Sprint 3,
--   mcp_page_charters Sprint 3, mcp_route_migration_state Sprint 5)
--   estão COMENTADAS — reativar no Sprint correspondente.
-- =====================================================================

-- 1) Tabela de briefs gerados (snapshot histórico)
CREATE TABLE IF NOT EXISTS mcp_briefs (
    id              BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    content         MEDIUMTEXT   NOT NULL,
    token_count     INT          NOT NULL,
    source_hash     VARCHAR(64)  NOT NULL,   -- sha256 do payload do cache
    generator_ver   VARCHAR(16)  NOT NULL DEFAULT 'v1',
    cost_usd        DECIMAL(8,4),
    valid           TINYINT(1)   NOT NULL DEFAULT 1,
    error_msg       TEXT,
    CONSTRAINT mcp_briefs_token_limit CHECK (token_count <= 3500),
    INDEX idx_mcp_briefs_generated_at (generated_at DESC),
    INDEX idx_mcp_briefs_valid_recent (valid, generated_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Tabela de telemetria de skills (camada L3)
--    Necessária pro brief mostrar uso 7d.
CREATE TABLE IF NOT EXISTS mcp_skill_telemetry (
    id                    BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    skill_name            VARCHAR(128) NOT NULL,
    agent_id              VARCHAR(128) NOT NULL,    -- claude-felipe-laptop, etc.
    triggered_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success               TINYINT(1)   NOT NULL DEFAULT 1,
    tokens_saved_estimate INT,
    context_payload       JSON,
    INDEX idx_skill_telemetry_recent (skill_name, triggered_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Tabela CACHE agregadora — substitui MATERIALIZED VIEW.
--    Singleton: SEMPRE 1 linha (singleton_id=1).
--    Atualizada por TRUNCATE+INSERT pelo command brief:generate
--    imediatamente antes da chamada Brain B (ver §03-prompt-generator).
CREATE TABLE IF NOT EXISTS mcp_brief_inputs_cache (
    singleton_id            TINYINT     NOT NULL DEFAULT 1 PRIMARY KEY,
    computed_at             TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_cycle            JSON,
    hitl_pending            JSON,
    brain_b_budget          JSON,
    in_flight               JSON,         -- TODO Sprint 3: ATIVAR após criar mcp_design_locks
    recent_24h              JSON,
    skills_7d               JSON,
    skills_candidatas_poda  JSON,
    charters_stale          JSON,         -- TODO Sprint 3: ATIVAR após criar mcp_page_charters
    flags                   JSON,         -- TODO Sprint 5: subcampo migration_aging_critical depende de mcp_route_migration_state
    CONSTRAINT mcp_brief_inputs_cache_singleton CHECK (singleton_id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garante a linha singleton (idempotente)
INSERT IGNORE INTO mcp_brief_inputs_cache (singleton_id) VALUES (1);

-- 4) Stored procedure pra refresh do cache.
--    Chamada pelo command brief:generate antes de invocar Brain B.
--    TRUNCATE+INSERT é mais simples que UPDATE com 10 subqueries
--    (transação curta, locks mínimos).
DELIMITER $$

DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache$$

CREATE PROCEDURE refresh_brief_inputs_cache()
BEGIN
    DECLARE v_active_cycle JSON;
    DECLARE v_hitl_pending JSON;
    DECLARE v_brain_b_budget JSON;
    DECLARE v_recent_24h JSON;
    DECLARE v_skills_7d JSON;
    DECLARE v_skills_poda JSON;

    -- Cycle ativo (ADR 0070: mcp_cycles)
    SELECT JSON_OBJECT(
        'id', id,
        'codename', codename,
        'sprint_label', sprint_label,
        'started_at', started_at,
        'ends_at', ends_at,
        'mission_focus', mission_focus
    ) INTO v_active_cycle
    FROM mcp_cycles
    WHERE status = 'active'
    ORDER BY started_at DESC
    LIMIT 1;

    -- HITL pendentes pra Wagner (ADR 0070: mcp_tasks)
    SELECT JSON_ARRAYAGG(JSON_OBJECT(
        'id', id,
        'title', title,
        'domain', domain,
        'escalated_at', escalated_at,
        'requested_by_agent', requested_by_agent,
        'urgency', urgency
    )) INTO v_hitl_pending
    FROM (
        SELECT id, title, domain, escalated_at, requested_by_agent, urgency
        FROM mcp_tasks
        WHERE status = 'pending_hitl'
          AND assigned_to = 'wagner'
        ORDER BY urgency DESC, escalated_at ASC
        LIMIT 10
    ) t;

    -- Brain B budget hoje (mcp_ads_decisions)
    SELECT JSON_OBJECT(
        'spent_usd', COALESCE(SUM(cost_usd), 0),
        'cap_usd', 50,
        'pct_used', LEAST(COALESCE(SUM(cost_usd), 0) / 50.0, 1.0)
    ) INTO v_brain_b_budget
    FROM mcp_ads_decisions
    WHERE brain_used = 'brain_b'
      AND created_at >= DATE(NOW());

    -- Decisões recentes 24h
    SET v_recent_24h = JSON_OBJECT(
        'adrs_approved', (
            SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'title', title))
            FROM mcp_memory_documents
            WHERE kind = 'adr'
              AND status = 'approved'
              AND approved_at > NOW() - INTERVAL 24 HOUR
        ),
        'commits_count', (
            SELECT COUNT(*)
            FROM mcp_audit_log
            WHERE event_type = 'github.commit'
              AND created_at > NOW() - INTERVAL 24 HOUR
        ),
        'ads_escalations', (
            SELECT COUNT(*)
            FROM mcp_ads_decisions
            WHERE outcome IN ('REQUIRE_HUMAN_REVIEW', 'BLOCK_ALWAYS')
              AND created_at > NOW() - INTERVAL 24 HOUR
        ),
        'incidents', (
            SELECT COUNT(*)
            FROM mcp_audit_log
            WHERE event_type = 'incident.opened'
              AND created_at > NOW() - INTERVAL 24 HOUR
        )
    );

    -- Skills uso 7d
    SELECT JSON_ARRAYAGG(JSON_OBJECT(
        'skill_name', skill_name,
        'trigger_count', trigger_count,
        'success_count', success_count,
        'tokens_saved', tokens_saved
    )) INTO v_skills_7d
    FROM (
        SELECT
            skill_name,
            COUNT(*) AS trigger_count,
            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS success_count,
            SUM(tokens_saved_estimate) AS tokens_saved
        FROM mcp_skill_telemetry
        WHERE triggered_at > NOW() - INTERVAL 7 DAY
        GROUP BY skill_name
        ORDER BY trigger_count DESC
        LIMIT 10
    ) s;

    -- Skills candidatas a poda (zero disparos 30d)
    SELECT JSON_ARRAYAGG(skill_name) INTO v_skills_poda
    FROM (
        SELECT DISTINCT skill_name
        FROM mcp_skill_telemetry
        WHERE skill_name NOT IN (
            SELECT DISTINCT skill_name
            FROM mcp_skill_telemetry
            WHERE triggered_at > NOW() - INTERVAL 30 DAY
        )
    ) s;

    -- ─────────────────────────────────────────────────────────────────
    -- TODO Sprint 3 — ATIVAR após criar mcp_design_locks:
    --
    -- DECLARE v_in_flight JSON;
    -- SELECT JSON_ARRAYAGG(JSON_OBJECT(
    --     'actor_id', actor_id,
    --     'actor_kind', actor_kind,
    --     'target_path', target_path,
    --     'intent_label', intent_label,
    --     'started_at', started_at,
    --     'aging_seconds', TIMESTAMPDIFF(SECOND, started_at, NOW()),
    --     'lock_expires_at', lock_expires_at
    -- )) INTO v_in_flight
    -- FROM (
    --     SELECT actor_id, actor_kind, target_path, intent_label,
    --            started_at, lock_expires_at
    --     FROM mcp_design_locks
    --     WHERE released_at IS NULL
    --       AND (lock_expires_at IS NULL OR lock_expires_at > NOW())
    --     ORDER BY started_at DESC
    --     LIMIT 20
    -- ) w;
    --
    -- ─────────────────────────────────────────────────────────────────
    -- TODO Sprint 3 — ATIVAR após criar mcp_page_charters:
    --
    -- DECLARE v_charters_stale JSON;
    -- SELECT JSON_ARRAYAGG(JSON_OBJECT(
    --     'charter_id', charter_id,
    --     'owner', owner,
    --     'last_verified', last_verified,
    --     'days_stale', TIMESTAMPDIFF(DAY, last_verified, NOW())
    -- )) INTO v_charters_stale
    -- FROM (
    --     SELECT charter_id, owner, last_verified
    --     FROM mcp_page_charters
    --     WHERE last_verified < NOW() - INTERVAL 60 DAY
    --     ORDER BY last_verified ASC
    --     LIMIT 10
    -- ) c;
    --
    -- ─────────────────────────────────────────────────────────────────
    -- TODO Sprint 5 — subcampo migration_aging_critical do flags JSON:
    --
    -- 'migration_aging_critical', (
    --     SELECT COUNT(*) FROM mcp_route_migration_state
    --     WHERE state = 'CANARY'
    --       AND state_since < NOW() - INTERVAL 14 DAY
    -- )
    -- ─────────────────────────────────────────────────────────────────

    -- Flags semáforo (Sprint 1: só prs_stale + visual_regression)
    SET @v_flags = JSON_OBJECT(
        'migration_aging_critical', 0,  -- TODO Sprint 5
        'prs_stale_3d', (
            SELECT COUNT(*) FROM mcp_audit_log a
            WHERE a.event_type = 'github.pr_opened'
              AND a.created_at < NOW() - INTERVAL 3 DAY
              AND NOT EXISTS (
                SELECT 1 FROM mcp_audit_log a2
                WHERE a2.related_id = a.related_id
                  AND a2.event_type IN ('github.pr_merged','github.pr_closed')
              )
        ),
        'visual_regression_failures_24h', (
            SELECT COUNT(*) FROM mcp_audit_log
            WHERE event_type = 'visual_regression.failed'
              AND created_at > NOW() - INTERVAL 24 HOUR
        )
    );

    -- TRUNCATE+INSERT singleton (mais simples que UPDATE com 9 SETs).
    -- Em produção considerar START TRANSACTION + ROLLBACK on error.
    TRUNCATE TABLE mcp_brief_inputs_cache;

    INSERT INTO mcp_brief_inputs_cache (
        singleton_id,
        computed_at,
        active_cycle,
        hitl_pending,
        brain_b_budget,
        in_flight,
        recent_24h,
        skills_7d,
        skills_candidatas_poda,
        charters_stale,
        flags
    ) VALUES (
        1,
        NOW(),
        v_active_cycle,
        v_hitl_pending,
        v_brain_b_budget,
        NULL,                  -- TODO Sprint 3: v_in_flight
        v_recent_24h,
        v_skills_7d,
        v_skills_poda,
        NULL,                  -- TODO Sprint 3: v_charters_stale
        @v_flags
    );
END$$

DELIMITER ;

-- 5) Stored procedure pra fetch do brief atual (usada pelo handler MCP)
DELIMITER $$

DROP PROCEDURE IF EXISTS get_current_brief$$

CREATE PROCEDURE get_current_brief()
BEGIN
    SELECT
        b.id,
        b.generated_at,
        b.content,
        b.token_count,
        TIMESTAMPDIFF(MINUTE, b.generated_at, NOW()) AS staleness_minutes
    FROM mcp_briefs b
    WHERE b.valid = 1
    ORDER BY b.generated_at DESC
    LIMIT 1;
END$$

DELIMITER ;

-- 6) Down migration (rollback)
-- DROP PROCEDURE IF EXISTS get_current_brief;
-- DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache;
-- DROP TABLE IF EXISTS mcp_brief_inputs_cache;
-- DROP TABLE IF EXISTS mcp_skill_telemetry;
-- DROP TABLE IF EXISTS mcp_briefs;
