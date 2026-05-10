<?php

declare(strict_types=1);

namespace Modules\Jana\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpProject;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Cria projects canônicos do oimpresso (1 por módulo) + workflow global default.
 *
 * Idempotente: usa firstOrCreate.
 *
 * Uso:
 *   php artisan db:seed --class=Modules\\Jana\\Database\\Seeders\\McpDefaultsSeeder
 */
class McpDefaultsSeeder extends Seeder
{
    /**
     * Projects canônicos. Mantém 1:1 com Modules/<X> + áreas transversais.
     * lead_user_id fica null no seed inicial (Wagner pode atribuir depois).
     */
    protected array $projects = [
        ['key' => 'COPI',     'name' => 'Copiloto IA',          'color' => 'indigo',   'icon' => 'bot'],
        ['key' => 'NFSE',     'name' => 'NFSe / Nota Fiscal',   'color' => 'amber',    'icon' => 'file-text'],
        ['key' => 'FIN',      'name' => 'Financeiro',           'color' => 'emerald',  'icon' => 'banknote'],
        ['key' => 'INFRA',    'name' => 'Infra / DevOps',       'color' => 'slate',    'icon' => 'server'],
        ['key' => 'PONTO',    'name' => 'PontoWr2',             'color' => 'sky',      'icon' => 'clock'],
        ['key' => 'CMS',      'name' => 'CMS / Landing',        'color' => 'rose',     'icon' => 'layout'],
        ['key' => 'MEMCOFRE', 'name' => 'MemCofre',             'color' => 'violet',   'icon' => 'archive'],
        ['key' => 'CRM',      'name' => 'CRM',                  'color' => 'orange',   'icon' => 'users'],
        ['key' => 'ACCO',     'name' => 'Accounting',           'color' => 'teal',     'icon' => 'calculator'],
        ['key' => 'REC',      'name' => 'RecurringBilling',     'color' => 'cyan',     'icon' => 'repeat'],
        ['key' => 'NFE',      'name' => 'NfeBrasil',            'color' => 'yellow',   'icon' => 'receipt'],
        ['key' => 'OFFICE',   'name' => 'Officeimpresso',       'color' => 'stone',    'icon' => 'building'],
        ['key' => 'ADS',      'name' => 'ADS',                  'color' => 'purple',   'icon' => 'megaphone'],
        ['key' => 'REPAIR',   'name' => 'Repair',               'color' => 'red',      'icon' => 'wrench'],
        ['key' => 'CONSULTA', 'name' => 'ConsultaOs',           'color' => 'lime',     'icon' => 'search'],
        ['key' => 'GROW',     'name' => 'Grow',                 'color' => 'green',    'icon' => 'trending-up'],
        ['key' => 'EVO',      'name' => 'EvolutionAgent',       'color' => 'fuchsia',  'icon' => 'workflow'],
        ['key' => 'TR',       'name' => 'TaskRegistry (meta)',  'color' => 'gray',     'icon' => 'kanban'],
        ['key' => 'COMVIS',   'name' => 'ComunicacaoVisual',    'color' => 'pink',     'icon' => 'palette'],
        ['key' => 'VEST',     'name' => 'Vestuario',            'color' => 'rose',     'icon' => 'shirt'],
        ['key' => 'AUTO',     'name' => 'OficinaAuto',          'color' => 'zinc',     'icon' => 'car'],
        ['key' => 'AUDIT',    'name' => 'Auditoria',            'color' => 'amber',    'icon' => 'shield-check'],
    ];

    /**
     * Workflow global default — copia statuses do mcp_tasks.status enum.
     * Categories: todo (pre-work), in-progress (active), done (closed).
     */
    protected array $defaultWorkflow = [
        'name' => 'Workflow Default',
        'statuses' => [
            ['key' => 'backlog',   'name' => 'Backlog',    'category' => 'todo',        'color' => 'gray'],
            ['key' => 'todo',      'name' => 'A fazer',    'category' => 'todo',        'color' => 'slate'],
            ['key' => 'doing',     'name' => 'Em curso',   'category' => 'in-progress', 'color' => 'blue'],
            ['key' => 'review',    'name' => 'Em review',  'category' => 'in-progress', 'color' => 'amber'],
            ['key' => 'blocked',   'name' => 'Bloqueada',  'category' => 'in-progress', 'color' => 'red'],
            ['key' => 'done',      'name' => 'Concluída',  'category' => 'done',        'color' => 'emerald'],
            ['key' => 'cancelled', 'name' => 'Cancelada',  'category' => 'done',        'color' => 'gray'],
        ],
        'transitions' => [
            'backlog'   => ['todo', 'cancelled'],
            'todo'      => ['doing', 'blocked', 'cancelled'],
            'doing'     => ['review', 'blocked', 'todo', 'cancelled'],
            'review'    => ['done', 'doing', 'blocked'],
            'blocked'   => ['todo', 'doing', 'cancelled'],
            'done'      => ['review'], // reabrir
            'cancelled' => ['todo'],   // reabrir
        ],
    ];

    public function run(): void
    {
        $this->command?->info('McpDefaultsSeeder — projects + workflow default');

        // 1. Workflow global default (se ainda não existe)
        $wfId = DB::table('mcp_workflows')
            ->whereNull('project_id')
            ->where('is_default', true)
            ->value('id');

        if (! $wfId) {
            $wfId = DB::table('mcp_workflows')->insertGetId([
                'project_id'  => null,
                'name'        => $this->defaultWorkflow['name'],
                'statuses'    => json_encode($this->defaultWorkflow['statuses']),
                'transitions' => json_encode($this->defaultWorkflow['transitions']),
                'is_default'  => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $this->command?->line("  ✅ Workflow global default criado (id={$wfId})");
        } else {
            $this->command?->line("  – Workflow global default já existe (id={$wfId})");
        }

        // 2. Projects (idempotente)
        $criados = 0;
        foreach ($this->projects as $proj) {
            $project = McpProject::firstOrCreate(
                ['key' => $proj['key']],
                array_merge($proj, [
                    'description'         => null,
                    'lead_user_id'        => null,
                    'status'              => 'active',
                    'settings'            => [],
                    'default_workflow_id' => $wfId,
                    'custom_field_schema' => [],
                    'next_task_number'    => 1,
                ])
            );
            if ($project->wasRecentlyCreated) {
                $criados++;
            }
        }

        $this->command?->info("  ✅ {$criados} projects novos criados (total: " . McpProject::count() . ').');
    }
}
