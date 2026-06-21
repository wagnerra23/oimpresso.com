<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Imutabilidade de mcp_task_events (TaskRegistry audit log de eventos por task).
 *
 * mcp_task_events foi criada com comentário "append-only — nunca UPDATE/DELETE"
 * (ver 2026_05_01_120002_create_mcp_task_events_table.php) mas sem trigger MySQL
 * enforçando — append-only era só convenção. Essa migration adiciona triggers
 * BEFORE UPDATE e BEFORE DELETE que sinalizam erro irrecuperável caso alguém
 * tente modificar o log.
 *
 * Espelho 1:1 do enforcement já aplicado em mcp_audit_log (Constituição Artigo 9):
 * Modules/Jana/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php
 *
 * Pattern idêntico ao usado em ponto_marcacoes (Portaria 671/2021) — ver
 * Modules/PontoWr2/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php
 *
 * Referências:
 * - ADR 0079 — Constituição Artigo 9 (Auditoria mandatória)
 * - ADR 0084 — Triggers MySQL append-only enforcement
 * - ADR 0278 — SDD governance ledger
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop em caso de re-run (idempotência)
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_task_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_task_events_no_delete');

        DB::unprepared("
            CREATE TRIGGER trg_mcp_task_events_no_update
            BEFORE UPDATE ON mcp_task_events
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_task_events is append-only (TaskRegistry audit log). UPDATE forbidden.';
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_mcp_task_events_no_delete
            BEFORE DELETE ON mcp_task_events
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_task_events is append-only (TaskRegistry audit log). DELETE forbidden.';
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_task_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_task_events_no_delete');
    }
};
