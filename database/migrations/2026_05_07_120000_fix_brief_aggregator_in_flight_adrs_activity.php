<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BRIEF-A1 — fix 3 sintomas detectados em prod 2026-05-07 ao auditar
 * o L7 Daily Brief (US-COPI-088, sessão CYCLE-02 W20).
 *
 * Sintoma observado: brief gerado tem ~217 tokens em vez dos 3000 alvo
 * porque seções inteiras saem vazias.
 *
 * Causas raíz:
 *
 * 1) `recent_24h.adrs_approved` SEMPRE NULL — query usava
 *    `decided_at > NOW() - INTERVAL 24 HOUR`. Coluna `decided_at` é DATE
 *    (não DATETIME); comparação trunca pra meia-noite. ADR aprovada ontem
 *    com decided_at='2026-05-06' fica fora da janela "últimas 24h" se
 *    rodar hoje após 00:00. Fix: usar `decided_at >= CURDATE() - INTERVAL 1 DAY`
 *    (cobre ontem+hoje, semântica de "decisões recentes" do brief).
 *
 * 2) `recent_24h.commits_count` SEMPRE 0 — query buscava
 *    `mcp_audit_log WHERE tool_or_resource = 'github.commit'`. Mas
 *    `mcp_audit_log` é log de requests MCP API (initialize, tools/call),
 *    NÃO recebe webhook GitHub. Esse contador nunca funcionou. Substituído
 *    por `mcp_activity_24h` (chamadas MCP), distinct_tools, distinct_users
 *    — sinal de vida real do time consumindo o sistema.
 *
 * 3) `in_flight` SEMPRE NULL — campo era hardcoded NULL no INSERT
 *    (TODO Sprint 3 referenciava `mcp_design_locks` que ainda não existe).
 *    Pivot: usar `mcp_tasks WHERE status IN ('doing','review')` como
 *    proxy. Mostra tasks que efetivamente estão sendo trabalhadas pelo
 *    time, com identifier+title+owner+aging.
 *
 * Refs: ADR 0091, US-COPI-088, sessão BRIEF-A1 2026-05-07
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE refresh_brief_inputs_cache()
            BEGIN
                DECLARE v_active_cycle JSON;
                DECLARE v_hitl_pending JSON;
                DECLARE v_in_flight JSON;
                DECLARE v_recent_24h JSON;
                DECLARE v_skills_7d JSON;
                DECLARE v_skills_poda JSON;

                -- Cycle ativo
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

                -- HITL pending: tasks blocked do Wagner
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

                -- BRIEF-A1 fix #3: in_flight populado de mcp_tasks doing+review.
                -- Pivot do TODO Sprint 3 (mcp_design_locks ainda não existe).
                SELECT JSON_ARRAYAGG(JSON_OBJECT(
                    'identifier', identifier,
                    'title', title,
                    'status', status,
                    'owner', owner,
                    'module', module,
                    'priority', priority,
                    'aging_hours', TIMESTAMPDIFF(HOUR, COALESCE(started_at, updated_at), NOW())
                )) INTO v_in_flight
                FROM (
                    SELECT identifier, title, status, owner, module, priority, started_at, updated_at
                    FROM mcp_tasks
                    WHERE status IN ('doing', 'review')
                    ORDER BY updated_at DESC
                    LIMIT 10
                ) wf;

                -- BRIEF-A1 fix #1+#2: recent_24h corrigido.
                -- adrs_approved: CURDATE() - 1 DAY (cobre ontem+hoje, evita bug DATE-vs-DATETIME).
                -- commits_count → mcp_activity_24h (audit log real, não github inexistente).
                SET v_recent_24h = JSON_OBJECT(
                    'adrs_approved', (
                        SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'slug', slug, 'title', title))
                        FROM mcp_memory_documents
                        WHERE type = 'adr'
                          AND status = 'aceito'
                          AND decided_at >= CURDATE() - INTERVAL 1 DAY
                    ),
                    'mcp_activity_24h', (
                        SELECT COUNT(*)
                        FROM mcp_audit_log
                        WHERE created_at > NOW() - INTERVAL 24 HOUR
                          AND status = 'ok'
                          AND tool_or_resource IS NOT NULL
                    ),
                    'mcp_distinct_tools_24h', (
                        SELECT COUNT(DISTINCT tool_or_resource)
                        FROM mcp_audit_log
                        WHERE created_at > NOW() - INTERVAL 24 HOUR
                          AND status = 'ok'
                          AND tool_or_resource IS NOT NULL
                    ),
                    'mcp_distinct_users_24h', (
                        SELECT COUNT(DISTINCT user_id)
                        FROM mcp_audit_log
                        WHERE created_at > NOW() - INTERVAL 24 HOUR
                          AND user_id IS NOT NULL
                    ),
                    'ads_escalations', 0,
                    'incidents', 0
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

                SET @v_flags = JSON_OBJECT(
                    'migration_aging_critical', 0,
                    'prs_stale_3d', 0,
                    'visual_regression_failures_24h', 0
                );

                SET @v_brain_b_budget = JSON_OBJECT(
                    'spent_usd', 0,
                    'cap_usd', 50,
                    'pct_used', 0
                );

                TRUNCATE TABLE mcp_brief_inputs_cache;

                INSERT INTO mcp_brief_inputs_cache (
                    singleton_id, computed_at, active_cycle, hitl_pending,
                    brain_b_budget, in_flight, recent_24h, skills_7d,
                    skills_candidatas_poda, charters_stale, flags
                ) VALUES (
                    1, NOW(), v_active_cycle, v_hitl_pending,
                    @v_brain_b_budget, v_in_flight, v_recent_24h, v_skills_7d,
                    v_skills_poda, NULL, @v_flags
                );
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS refresh_brief_inputs_cache');
        // Rollback re-aplica a migration anterior (2026_05_06_172445).
        // Não re-cria aqui pra evitar duplicação de SQL — usar:
        //   php artisan migrate:rollback --step=1
        //   php artisan migrate (re-roda 172445)
    }
};
