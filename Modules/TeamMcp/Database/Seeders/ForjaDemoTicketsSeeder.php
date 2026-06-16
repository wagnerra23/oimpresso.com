<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ForjaDemoTicketsSeeder — propostas-semente da Triagem do cockpit Forja (/forja).
 *
 * Por que existe (Onda Forja PR-A · aba Triagem real):
 *   A aba Triagem projeta `mcp_tasks` do project key=FORJA em estado de triagem
 *   (sem owner OU sem priority OU status=backlog). Sem dado, a tela nasce vazia
 *   ("Nada pra triar") e o screenshot não bate com a referência aprovada
 *   (memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md §1 Triagem).
 *   Este seeder garante o project FORJA + as 3 propostas-semente do protótipo.
 *
 * O que faz (IDEMPOTENTE):
 *   1. updateOrCreate do project key=FORJA em mcp_jira_projects (Jira-style, ADR 0070).
 *   2. updateOrCreate por task_id (FORJA-150/151/152) das 3 propostas-semente —
 *      status=backlog, owner=null, priority=null (logo: caem na triagem), com
 *      custom_fields JSON = { forja_tipo, forja_papel, forja_onda }.
 *   Rodar 2× NÃO duplica (chave estável por key/task_id).
 *
 * Taxonomia Forja (custom_fields, projeção — NÃO é schema novo · Tier 0):
 *   - forja_tipo : Tela | Bug | Refino (badge de tipo na linha)
 *   - forja_papel: ator que propôs (CC = Claude Code) — selo [CC]
 *   - forja_onda : agrupador de épico/lote (null = ainda em triagem, sem onda)
 *
 * Multi-tenant Tier 0 (ADR 0093 + ADR 0070):
 *   mcp_jira_projects / mcp_tasks são REPO-WIDE cross-tenant POR DESIGN (governança
 *   da plataforma) — SEM business_id. Igual Scorecard/Triage do ProjectMgmt.
 *
 * NÃO roda automaticamente em deploy — invocar explícito:
 *   php artisan db:seed --class="Modules\\TeamMcp\\Database\\Seeders\\ForjaDemoTicketsSeeder"
 *
 * @see memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md (§1 Triagem)
 * @see Modules\ProjectMgmt\Http\Controllers\TriageController (padrão da fila)
 */
class ForjaDemoTicketsSeeder extends Seeder
{
    /** Key do project Jira-style do cockpit Forja. */
    private const PROJECT_KEY = 'FORJA';

    /**
     * 3 propostas-semente — espelho 1:1 do protótipo aprovado.
     *
     * @return array<int,array{task_id:string,title:string,module:string,forja_tipo:string,forja_papel:string}>
     */
    private function tickets(): array
    {
        return [
            [
                'task_id'     => 'FORJA-152',
                'title'       => 'Busca semântica no KB (Meilisearch)',
                'module'      => 'KB',
                'forja_tipo'  => 'Tela',
                'forja_papel' => 'CC',
            ],
            [
                'task_id'     => 'FORJA-151',
                'title'       => 'Bug: drawer financeiro corta o valor no dark',
                'module'      => 'Financeiro',
                'forja_tipo'  => 'Bug',
                'forja_papel' => 'CC',
            ],
            [
                'task_id'     => 'FORJA-150',
                'title'       => 'Rename inbox → Atendimento na topnav',
                'module'      => 'Atendimento',
                'forja_tipo'  => 'Refino',
                'forja_papel' => 'CC',
            ],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('mcp_jira_projects') || ! Schema::hasTable('mcp_tasks')) {
            $this->command?->warn('mcp_jira_projects / mcp_tasks ausentes — rode php artisan migrate primeiro.');
            return;
        }

        // 1) Project FORJA (idempotente por key). next_task_number só sobe nunca
        //    abaixo do maior NNNN semeado, pra próximos identifiers não colidirem.
        $project = McpProject::updateOrCreate(
            ['key' => self::PROJECT_KEY],
            [
                'name'             => 'Forja — cowork loop',
                'description'      => 'Cockpit de observabilidade + governança do loop humano ↔ agente. Issues = projeção sobre mcp_tasks (ADR 0070).',
                'status'           => 'active',
                'icon'             => 'Hammer',
                'next_task_number' => 153, // > maior NNNN semeado (152), evita colisão
            ]
        );

        $criados     = 0;
        $atualizados = 0;

        // 2) 3 propostas-semente (idempotente por task_id). Estado de triagem:
        //    status=backlog + owner=null + priority=null → McpTask::triage() pega.
        foreach ($this->tickets() as $t) {
            $attributes = [
                'project_id'    => $project->id,
                'identifier'    => $t['task_id'], // Linear-style já no formato FORJA-NNN
                'module'        => $t['module'],
                'title'         => $t['title'],
                'status'        => 'backlog',
                'type'          => 'task',
                'owner'         => null,
                'priority'      => null,
                'custom_fields' => [
                    'forja_tipo'  => $t['forja_tipo'],
                    'forja_papel' => $t['forja_papel'],
                    'forja_onda'  => null, // null = ainda em triagem, sem onda atribuída
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
            "ForjaDemoTicketsSeeder: project {$project->key} ok · tickets {$criados} criados, {$atualizados} atualizados (de 3 propostas-semente)."
        );
    }
}
