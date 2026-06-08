<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Habilita 3 módulos verticais como projects canônicos no MCP (mcp_jira_projects):
 *   - COMVIS — ComunicacaoVisual (em construção, piloto Q3)
 *   - VEST   — Vestuario (em produção, piloto ROTA LIVRE biz=4 SC)
 *   - AUTO   — OficinaAuto (backlog feature-wish até sinal qualificado per ADR 0105)
 *
 * Refs:
 *   - ADR 0070 — Jira-style task management
 *   - ADR 0121 — oimpresso modular especializado por vertical
 *   - ADR 0105 — cliente como sinal qualificado
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

        if (! $defaultWorkflowId) {
            // Workflow default ainda não existe (caso o McpDefaultsSeeder nunca tenha rodado).
            // Migração não cria workflow — só projects. Se o workflow faltar, fica null
            // e o seeder seta depois. Não bloqueia migrate.
            $defaultWorkflowId = null;
        }

        $projects = [
            [
                'key'         => 'COMVIS',
                'name'        => 'ComunicacaoVisual',
                'description' => 'Vertical comunicação visual / gráfica rápida (em construção, piloto Q3 2026 — 1 dos 6 saudáveis OfficeImpresso a confirmar).',
                'color'       => 'pink',
                'icon'        => 'palette',
            ],
            [
                'key'         => 'VEST',
                'name'        => 'Vestuario',
                'description' => 'Vertical vestuário / loja de roupa (em produção, cliente piloto ROTA LIVRE biz=4 Termas do Gravatal/SC).',
                'color'       => 'rose',
                'icon'        => 'shirt',
            ],
            [
                'key'         => 'AUTO',
                'name'        => 'OficinaAuto',
                'description' => 'Vertical oficina mecânica / centro automotivo (backlog feature-wish até sinal qualificado per ADR 0105 — candidato Martinho Caçambas a confirmar).',
                'color'       => 'zinc',
                'icon'        => 'car',
            ],
        ];

        foreach ($projects as $proj) {
            DB::table('mcp_jira_projects')->updateOrInsert(
                ['key' => $proj['key']],
                array_merge($proj, [
                    'lead_user_id'        => null,
                    'status'              => 'active',
                    'settings'            => json_encode([]),
                    'default_workflow_id' => $defaultWorkflowId,
                    'custom_field_schema' => json_encode([]),
                    'next_task_number'    => 1,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        // Reverte só se nunca tiveram tasks atribuídas (segurança).
        $keys = ['COMVIS', 'VEST', 'AUTO'];

        foreach ($keys as $key) {
            $projectId = DB::table('mcp_jira_projects')->where('key', $key)->value('id');
            if (! $projectId) {
                continue;
            }

            $hasTasks = DB::table('mcp_tasks')->where('project_id', $projectId)->exists();
            if ($hasTasks) {
                throw new RuntimeException(
                    "Não posso reverter project '{$key}' (id={$projectId}) — já tem tasks. " .
                    'Mover/arquivar tasks antes de re-rodar migrate:rollback.'
                );
            }

            DB::table('mcp_jira_projects')->where('id', $projectId)->delete();
        }
    }
};
