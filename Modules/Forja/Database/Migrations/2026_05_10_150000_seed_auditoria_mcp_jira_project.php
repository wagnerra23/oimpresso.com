<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Habilita Modules/Auditoria como project canônico no MCP (mcp_jira_projects).
 *
 *   - AUDIT — Auditoria (governança transversal — UI rica + undo sobre activity_log existente)
 *
 * Refs:
 *   - ADR 0070 — Jira-style task management
 *   - ADR 0125 (mcp-jira-projects-modulos-verticais) — padrão de adição de projects canônicos
 *   - ADR 0127 — Modules/Auditoria UI + undo (este project é home das US-AUDIT-001..010)
 *   - McpDefaultsSeeder — seeder espelhado, mantém consistência em refresh
 *
 * Idempotente: usa updateOrInsert com chave única `key`.
 */
return new class extends Migration {
    public function up(): void
    {
        $defaultWorkflowId = DB::table('mcp_workflows')
            ->whereNull('project_id')
            ->where('is_default', true)
            ->value('id');

        DB::table('mcp_jira_projects')->updateOrInsert(
            ['key' => 'AUDIT'],
            [
                'key'                 => 'AUDIT',
                'name'                => 'Auditoria',
                'description'         => 'Camada de governança transversal — UI rica /auditoria + undo sobre activity_log existente. Distingue User vs IA via causer_kind. Whitelist UNREVERTIBLE de 5 categorias. Per ADR 0127.',
                'color'               => 'amber',
                'icon'                => 'shield-check',
                'lead_user_id'        => null,
                'status'              => 'active',
                'settings'            => json_encode([]),
                'default_workflow_id' => $defaultWorkflowId,
                'custom_field_schema' => json_encode([]),
                'next_task_number'    => 1,
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );
    }

    public function down(): void
    {
        $projectId = DB::table('mcp_jira_projects')->where('key', 'AUDIT')->value('id');
        if (! $projectId) {
            return;
        }

        $hasTasks = DB::table('mcp_tasks')->where('project_id', $projectId)->exists();
        if ($hasTasks) {
            throw new RuntimeException(
                "Não posso reverter project 'AUDIT' (id={$projectId}) — já tem tasks. " .
                'Mover/arquivar tasks antes de re-rodar migrate:rollback.'
            );
        }

        DB::table('mcp_jira_projects')->where('id', $projectId)->delete();
    }
};
