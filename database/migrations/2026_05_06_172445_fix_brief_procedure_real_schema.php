<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix do procedure refresh_brief_inputs_cache pra usar schema REAL.
 *
 * Bug detectado em prod ao rodar `brief:generate --dry-run`:
 *   SQLSTATE[42S22]: Unknown column 'codename' in 'SELECT'
 *
 * O procedure foi escrito a partir do dossier do Sonnet, que usou nomes
 * GENÉRICOS de colunas. O schema real (mcp_cycles, mcp_tasks de
 * 2026_05_04_180003_create_mcp_cycles_table.php e
 * extend_mcp_tasks_for_jira_style.php — ADR 0070) usa nomes diferentes:
 *
 * | Dossier               | Schema real              |
 * |-----------------------|--------------------------|
 * | mcp_cycles.codename   | mcp_cycles.key           |
 * | mcp_cycles.sprint_label | mcp_cycles.name        |
 * | mcp_cycles.started_at | mcp_cycles.start_date    |
 * | mcp_cycles.ends_at    | mcp_cycles.end_date      |
 * | mcp_cycles.mission_focus | mcp_cycles.goal       |
 * | mcp_tasks.assigned_to | mcp_tasks.owner          |
 * | mcp_tasks.urgency     | mcp_tasks.priority       |
 * | mcp_tasks.escalated_at| mcp_tasks.created_at     |
 * | mcp_tasks.domain      | mcp_tasks.module         |
 * | mcp_memory_documents.kind | .type                |
 * | mcp_memory_documents.approved_at | .decided_at   |
 * | mcp_memory_documents.status='approved' | ='aceito'|
 *
 * Tabelas que NÃO existem ainda em prod (omitidas/zeradas):
 * - mcp_ads_decisions (ADS budget e escalations → 0)
 * - mcp_inbox (canal ops → fallback Laravel Log no command)
 *
 * Status `pending_hitl` não existe em mcp_tasks — valores reais:
 * backlog, todo, doing, blocked, done, cancelled. Usar `blocked` como
 * proxy de HITL pending pra Wagner (status='blocked' AND owner='wagner').
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE refresh_brief_inputs_cache()
            BEGIN
                DECLARE v_active_cycle JSON;
                DECLARE v_hitl_pending JSON;
                DECLARE v_recent_24h JSON;
                DECLARE v_skills_7d JSON;
                DECLARE v_skills_poda JSON;

                -- Cycle ativo (mcp_cycles real: key, name, start_date, end_date, goal)
                SELECT JSON_OBJECT(
                    'id', id,
                    'key', `key`,
                    'name', name,
                    'start_date', start_date,
                    'end_date', end_date,
                    'goal', goal
                ) INTO v_active_cycle
                FROM mcp_cycles
                WHERE status = 'active'
                ORDER BY start_date DESC
                LIMIT 1;

                -- HITL pending: tasks blocked do Wagner (owner='wagner')
                -- mcp_tasks real: owner, priority, module
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'id', id,
                    'identifier', identifier,
                    'title', title,
                    'module', module,
                    'priority', priority,
                    'created_at', created_at
                )) INTO v_hitl_pending
                FROM (
                    SELECT id, identifier, title, module, priority, created_at
                    FROM mcp_tasks
                    WHERE status = 'blocked'
                      AND owner = 'wagner'
                    ORDER BY priority DESC, created_at ASC
                    LIMIT 10
                ) t;

                -- Decisões recentes 24h (mcp_memory_documents real:
                -- type, status='aceito', decided_at)
                SET v_recent_24h = JSON_OBJECT(
                    'adrs_approved', (
                        SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'slug', slug, 'title', title))
                        FROM mcp_memory_documents
                        WHERE type = 'adr'
                          AND status = 'aceito'
                          AND decided_at > NOW() - INTERVAL 24 HOUR
                    ),
                    'commits_count', (
                        SELECT COUNT(*)
                        FROM mcp_audit_log
                        WHERE tool_or_resource = 'github.commit'
                          AND created_at > NOW() - INTERVAL 24 HOUR
                    ),
                    'ads_escalations', 0,
                    'incidents', 0
                );

                -- Skills uso 7d (mcp_skill_telemetry mantém schema do Sprint 1)
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

                -- Skills candidatas a poda
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

                -- Flags semáforo (mcp_audit_log existe; campos: tool_or_resource, created_at)
                SET @v_flags = JSON_OBJECT(
                    'migration_aging_critical', 0,
                    'prs_stale_3d', 0,
                    'visual_regression_failures_24h', 0
                );

                -- Brain B budget: tabela mcp_ads_decisions ainda não existe.
                -- Retorna 0 hardcoded até Sprint que cria mcp_ads_decisions.
                SET @v_brain_b_budget = JSON_OBJECT(
                    'spent_usd', 0,
                    'cap_usd', 50,
                    'pct_used', 0
                );

                -- TRUNCATE+INSERT singleton
                TRUNCATE TABLE mcp_brief_inputs_cache;

                INSERT INTO mcp_brief_inputs_cache (
                    singleton_id, computed_at, active_cycle, hitl_pending,
                    brain_b_budget, in_flight, recent_24h, skills_7d,
                    skills_candidatas_poda, charters_stale, flags
                ) VALUES (
                    1, NOW(), v_active_cycle, v_hitl_pending,
                    @v_brain_b_budget, NULL, v_recent_24h, v_skills_7d,
                    v_skills_poda, NULL, @v_flags
                );
            END
        SQL);
    }

    public function down(): void
    {
        // Down volta pro procedure ORIGINAL (com schema do dossier).
        // Em prática: rollback total via 2026_05_06_170045_create_daily_brief_schema.
        DB::unprepared('DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache');
    }
};
