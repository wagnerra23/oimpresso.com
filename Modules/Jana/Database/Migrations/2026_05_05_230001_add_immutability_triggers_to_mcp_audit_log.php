<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P0.2 do audit cascata Constitution v1.1.0 — Artigo 9 (Auditoria mandatória):
 * "modificação de log = incidente P0".
 *
 * mcp_audit_log foi criada com comentário "append-only" mas sem trigger MySQL
 * enforçando. Essa migration adiciona triggers BEFORE UPDATE e BEFORE DELETE
 * que sinalizam erro irrecuperável caso alguém tente modificar o log.
 *
 * Pattern idêntico ao usado em ponto_marcacoes (Portaria 671/2021) — ver
 * Modules/PontoWr2/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php
 *
 * Referências:
 * - ADR 0079 — Constituição Artigo 9
 * - ADR 0080 — Audit cascata findings
 * - ADR 0084 — Triggers MySQL append-only enforcement
 * - memory/governance/audit-2026-05-05-v1.1.md (P0.2)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop em caso de re-run (idempotência)
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_audit_log_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_audit_log_no_delete');

        DB::unprepared("
            CREATE TRIGGER trg_mcp_audit_log_no_update
            BEFORE UPDATE ON mcp_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). UPDATE forbidden.';
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_mcp_audit_log_no_delete
            BEFORE DELETE ON mcp_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'mcp_audit_log is append-only (Constitution v1.1.0 Article 9). DELETE forbidden.';
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_audit_log_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_mcp_audit_log_no_delete');
    }
};
