<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 KB expansion (ADR 0053) — adiciona tipos ao ENUM mcp_memory_documents.type:
 * - comparativo (Capterra-style competitive briefs)
 * - audit (audits/*.md por módulo)
 * - runbook (RUNBOOK.md por módulo)
 * - changelog (CHANGELOG.md por módulo + repo raiz)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE mcp_memory_documents
            MODIFY COLUMN type ENUM(
                'adr', 'session', 'reference', 'spec',
                'handoff', 'current', 'tasks', 'other',
                'comparativo', 'audit', 'runbook', 'changelog'
            ) NOT NULL
        ");

        DB::statement("
            ALTER TABLE mcp_memory_documents_history
            MODIFY COLUMN type ENUM(
                'adr', 'session', 'reference', 'spec',
                'handoff', 'current', 'tasks', 'other',
                'comparativo', 'audit', 'runbook', 'changelog'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // Reverte rows com tipos novos pra 'other' antes de encolher enum
        DB::table('mcp_memory_documents')
            ->whereIn('type', ['comparativo', 'audit', 'runbook', 'changelog'])
            ->update(['type' => 'other']);

        DB::statement("
            ALTER TABLE mcp_memory_documents
            MODIFY COLUMN type ENUM('adr','session','reference','spec','handoff','current','tasks','other') NOT NULL
        ");
        DB::statement("
            ALTER TABLE mcp_memory_documents_history
            MODIFY COLUMN type ENUM('adr','session','reference','spec','handoff','current','tasks','other') NOT NULL
        ");
    }
};
