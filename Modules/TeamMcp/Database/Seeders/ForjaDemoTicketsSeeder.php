<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ForjaDemoTicketsSeeder — tickets-semente do cockpit Forja (/forja).
 *
 * Por que existe (Onda Forja · abas Triagem/Backlog/Quadro reais):
 *   As abas projetam `mcp_tasks` do project key=FORJA. Sem dado, as telas nascem
 *   vazias e o screenshot não bate com a referência aprovada
 *   (memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).
 *   Este seeder garante o project FORJA + o conjunto de tickets do protótipo:
 *     - 3 PROPOSTAS em triagem (sem owner/priority → caem na aba Triagem)
 *     - 11 ISSUES já triadas (onda/fase/papel/prioridade → Backlog + Quadro F0→F3.5)
 *
 * O que faz (IDEMPOTENTE): updateOrCreate do project + updateOrCreate por task_id.
 * Rodar 2× NÃO duplica (chave estável por key/task_id).
 *
 * Taxonomia Forja (custom_fields — projeção, NÃO é schema novo · Tier 0):
 *   - forja_tipo : Tela|Bug|Refino|Épico|ADR|Infra|Gate (badge de tipo)
 *   - forja_papel: ator (W=Wagner · CC=Claude Code · CL=Claude loop · CD=Claude Design)
 *   - forja_onda : agrupador de lote (null = ainda em triagem)
 *   - forja_fase : F0|F1|F1.5|F2|F3|F3.5 (coluna do Quadro · null = pré-board)
 *
 * Multi-tenant Tier 0 (ADR 0093 + 0070): mcp_jira_projects / mcp_tasks são
 *   REPO-WIDE cross-tenant POR DESIGN (governança da plataforma) — SEM business_id.
 *
 * NÃO roda em deploy automático — invocar explícito:
 *   php artisan db:seed --class="Modules\\TeamMcp\\Database\\Seeders\\ForjaDemoTicketsSeeder"
 *
 * @see memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md
 */
class ForjaDemoTicketsSeeder extends Seeder
{
    /** Key do project Jira-style do cockpit Forja. */
    private const PROJECT_KEY = 'FORJA';

    /**
     * Tickets-semente — espelho do protótipo aprovado.
     * onda/fase/owner/priority null + status=backlog ⇒ cai na Triagem.
     *
     * @return array<int,array{task_id:string,title:string,module:string,tipo:string,papel:string,onda:?string,fase:?string,status:string,owner:?string,priority:?string}>
     */
    private function tickets(): array
    {
        return [
            // --- 3 propostas em TRIAGEM (sem owner/priority) ---
            ['task_id' => 'FORJA-152', 'title' => 'Busca semântica no KB (Meilisearch)',        'module' => 'KB',          'tipo' => 'Tela',   'papel' => 'CC', 'onda' => null,        'fase' => null,    'status' => 'backlog', 'owner' => null,     'priority' => null],
            ['task_id' => 'FORJA-151', 'title' => 'Bug: drawer financeiro corta o valor no dark', 'module' => 'Financeiro', 'tipo' => 'Bug',    'papel' => 'CC', 'onda' => null,        'fase' => null,    'status' => 'backlog', 'owner' => null,     'priority' => null],
            ['task_id' => 'FORJA-150', 'title' => 'Rename inbox → Atendimento na topnav',        'module' => 'Atendimento','tipo' => 'Refino', 'papel' => 'CC', 'onda' => null,        'fase' => null,    'status' => 'backlog', 'owner' => null,     'priority' => null],

            // --- 11 issues já triadas (Backlog + Quadro) ---
            ['task_id' => 'FORJA-160', 'title' => 'Épico — Identidade única (roxo canon)',        'module' => 'Sistema',    'tipo' => 'Épico',  'papel' => 'CC', 'onda' => 'SEM ONDA',  'fase' => 'F1',   'status' => 'todo',    'owner' => 'wagner', 'priority' => 'p1'],
            ['task_id' => 'FORJA-142', 'title' => 'Sells/Create — P0 do piloto ROTA LIVRE',       'module' => 'Vendas',     'tipo' => 'Tela',   'papel' => 'CC', 'onda' => 'V1.1',      'fase' => 'F1',   'status' => 'todo',    'owner' => 'wagner', 'priority' => 'p0'],
            ['task_id' => 'FORJA-141', 'title' => 'Completar §TEMPERO na fundação',               'module' => 'Financeiro', 'tipo' => 'Infra',  'papel' => 'CL', 'onda' => 'FA-1',      'fase' => 'F3',   'status' => 'doing',   'owner' => 'claude', 'priority' => 'p1'],
            ['task_id' => 'FORJA-140', 'title' => 'Snap 314 font-size hardcoded ao ramp --fs',    'module' => 'Financeiro', 'tipo' => 'Refino', 'papel' => 'CL', 'onda' => 'FA-2',      'fase' => 'F3',   'status' => 'todo',    'owner' => 'claude', 'priority' => 'p2'],
            ['task_id' => 'FORJA-139', 'title' => 'G-3 E2E Playwright → pull_request + required', 'module' => 'Sistema',    'tipo' => 'Gate',   'papel' => 'CL', 'onda' => 'Q1',        'fase' => 'F3',   'status' => 'todo',    'owner' => 'claude', 'priority' => 'p1'],
            ['task_id' => 'FORJA-138', 'title' => 'Compras: ilha #1f3a5f → roxo canon',           'module' => 'Compras',    'tipo' => 'Refino', 'papel' => 'CL', 'onda' => 'SEM ONDA',  'fase' => 'F3',   'status' => 'todo',    'owner' => 'claude', 'priority' => 'p2'],
            ['task_id' => 'FORJA-137', 'title' => 'ADR — token --origin-DEV (selo da Forja)',     'module' => 'Sistema',    'tipo' => 'ADR',    'papel' => 'W',  'onda' => 'SEM ONDA',  'fase' => 'F0',   'status' => 'todo',    'owner' => 'wagner', 'priority' => 'p2'],
            ['task_id' => 'FORJA-136', 'title' => 'Financeiro — pilar Fiscal (parado em 5,5)',    'module' => 'Financeiro', 'tipo' => 'Tela',   'papel' => 'CC', 'onda' => 'SEM ONDA',  'fase' => 'F1',   'status' => 'blocked', 'owner' => 'wagner', 'priority' => 'p1'],
            ['task_id' => 'FORJA-135', 'title' => 'Rename ds-v5 → ds-v6 no git (ADR 0244)',       'module' => 'Sistema',    'tipo' => 'Infra',  'papel' => 'CL', 'onda' => 'SEM ONDA',  'fase' => 'F3',   'status' => 'todo',    'owner' => 'claude', 'priority' => 'p2'],
            ['task_id' => 'FORJA-134', 'title' => 'Painel de saúde — frescor do censo de gates',  'module' => 'Sistema',    'tipo' => 'Infra',  'papel' => 'CC', 'onda' => 'SEM ONDA',  'fase' => 'F1',   'status' => 'todo',    'owner' => 'wagner', 'priority' => 'p2'],
            ['task_id' => 'FORJA-133', 'title' => 'Sync now: endereço cliente ↔ venda',           'module' => 'Vendas',     'tipo' => 'Bug',    'papel' => 'CC', 'onda' => 'SEM ONDA',  'fase' => 'F1',   'status' => 'todo',    'owner' => 'wagner', 'priority' => 'p2'],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('mcp_jira_projects') || ! Schema::hasTable('mcp_tasks')) {
            $this->command?->warn('mcp_jira_projects / mcp_tasks ausentes — rode php artisan migrate primeiro.');
            return;
        }

        $project = McpProject::updateOrCreate(
            ['key' => self::PROJECT_KEY],
            [
                'name'             => 'Forja — cowork loop',
                'description'      => 'Cockpit de observabilidade + governança do loop humano ↔ agente. Issues = projeção sobre mcp_tasks (ADR 0070).',
                'status'           => 'active',
                'icon'             => 'Hammer',
                'next_task_number' => 161, // > maior NNNN semeado (160), evita colisão
            ]
        );

        $criados     = 0;
        $atualizados = 0;

        foreach ($this->tickets() as $t) {
            $attributes = [
                'project_id'    => $project->id,
                'identifier'    => $t['task_id'],
                'module'        => $t['module'],
                'title'         => $t['title'],
                'status'        => $t['status'],
                'type'          => 'task',
                'owner'         => $t['owner'],
                'priority'      => $t['priority'],
                'custom_fields' => [
                    'forja_tipo'  => $t['tipo'],
                    'forja_papel' => $t['papel'],
                    'forja_onda'  => $t['onda'],
                    'forja_fase'  => $t['fase'],
                ],
            ];

            $existing = McpTask::where('task_id', $t['task_id'])->first();

            if ($existing) {
                $existing->fill($attributes);
                if ($existing->isDirty()) {
                    $existing->save();
                    $atualizados++;
                }
            } else {
                McpTask::create(array_merge(['task_id' => $t['task_id']], $attributes));
                $criados++;
            }
        }

        $this->command?->info(
            "ForjaDemoTicketsSeeder: project {$project->key} ok · tickets {$criados} criados, {$atualizados} atualizados (de " . count($this->tickets()) . ' sementes).'
        );
    }
}
