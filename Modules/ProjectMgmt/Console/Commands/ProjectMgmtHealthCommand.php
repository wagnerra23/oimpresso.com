<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * project-mgmt:health — Health check do módulo ProjectMgmt (D9.c — Wave 17 saturação 97%).
 *
 * Equivalente leve do jana:health-check; foca em sinais críticos da gestão de
 * projects/tasks/cycles (Jira-style ADR 0070):
 *
 *   1. projects_table_present — mcp_projects presente
 *   2. tasks_table_present    — mcp_tasks presente
 *   3. active_projects        — projects status=active presentes (workload visível)
 *   4. unowned_tasks_p0       — tasks priority=p0 sem owner (debt operacional)
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global (admin global view)
 *   - Com --business: filtra explicitamente um business
 *
 * Read-only — NUNCA INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * Uso:
 *   php artisan project-mgmt:health
 *   php artisan project-mgmt:health --business=1
 *   php artisan project-mgmt:health --json --alert
 *
 * NOTA Tier 0: NUNCA `--verbose` custom (colide com Symfony Console).
 *
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules\Vestuario\Console\Commands\VestuarioHealthCommand (pattern referência)
 */
class ProjectMgmtHealthCommand extends Command
{
    protected $signature = 'project-mgmt:health
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check do módulo ProjectMgmt — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $businessId = $this->option('business') !== null ? (int) $this->option('business') : null;
        $asJson     = (bool) $this->option('json');
        $alert      = (bool) $this->option('alert');

        $checks = [
            $this->checkProjectsTable(),
            $this->checkTasksTable(),
            $this->checkActiveProjects($businessId),
            $this->checkUnownedP0Tasks($businessId),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            return $this->outputJson($checks, $summary, $businessId, $alert);
        }

        return $this->outputTable($checks, $summary, $businessId, $alert);
    }

    private function checkProjectsTable(): array
    {
        if (! Schema::hasTable('mcp_projects')) {
            return $this->makeCheck('projects_table_present', 'FAIL', 0, '1',
                'Tabela mcp_projects ausente',
                'Rode migrations Jana — ProjectMgmt opera sobre models McpProject.'
            );
        }

        return $this->makeCheck('projects_table_present', 'OK', 1, '1',
            'Tabela mcp_projects presente',
            'Schema canônico Jana aplicado.'
        );
    }

    private function checkTasksTable(): array
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return $this->makeCheck('tasks_table_present', 'FAIL', 0, '1',
                'Tabela mcp_tasks ausente',
                'Rode migrations Jana — tela Backlog depende dela.'
            );
        }

        return $this->makeCheck('tasks_table_present', 'OK', 1, '1',
            'Tabela mcp_tasks presente',
            'Schema canônico Jana aplicado.'
        );
    }

    /**
     * Check 3: existem projects status=active scoped por business?
     */
    private function checkActiveProjects(?int $businessId): array
    {
        if (! Schema::hasTable('mcp_projects')) {
            return $this->makeCheck('active_projects', 'WARN', null, '>=1', 'Tabela ausente', 'Rode migrate.');
        }

        $query = DB::table('mcp_projects')->where('status', 'active');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = (int) $query->count();

        if ($count === 0) {
            return $this->makeCheck('active_projects', 'WARN', 0, '>=1',
                'Nenhum project active' . ($businessId !== null ? " pra business_id={$businessId}" : ''),
                'Backlog parado OU todos projects em draft. Revise tela /ads/admin/projects.'
            );
        }

        return $this->makeCheck('active_projects', 'OK', $count, '>=1',
            "{$count} project(s) active",
            'Workload visível ao time.'
        );
    }

    /**
     * Check 4: tasks priority=p0 sem owner — alerta de debt operacional.
     */
    private function checkUnownedP0Tasks(?int $businessId): array
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return $this->makeCheck('unowned_tasks_p0', 'WARN', null, '0', 'Tabela ausente', 'Rode migrate.');
        }

        $query = DB::table('mcp_tasks')
            ->where('priority', 'p0')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNull('owner');

        // mcp_tasks pode não ter business_id direto (depende do schema Jana atual);
        // filtramos por project_id pertencente ao business quando aplicável.
        if ($businessId !== null && Schema::hasColumn('mcp_tasks', 'project_id')) {
            $projectIds = DB::table('mcp_projects')
                ->where('business_id', $businessId)
                ->pluck('id');
            $query->whereIn('project_id', $projectIds);
        }

        $count = (int) $query->count();

        if ($count >= 5) {
            return $this->makeCheck('unowned_tasks_p0', 'FAIL', $count, '<5',
                "{$count} tasks P0 SEM owner",
                'Debt crítico — atribua ownership. Rode `mcp-tasks-triage` ou tela /project-mgmt/backlog?priority=p0&owner=null.'
            );
        }

        if ($count > 0) {
            return $this->makeCheck('unowned_tasks_p0', 'WARN', $count, '0',
                "{$count} task(s) P0 SEM owner",
                'Atribua ownership pra evitar debt operacional.'
            );
        }

        return $this->makeCheck('unowned_tasks_p0', 'OK', 0, '0',
            'Todas tasks P0 com owner',
            'Operacional saudável.'
        );
    }

    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('ProjectMgmt Health Check — ' . now()->toDateTimeString());
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];
        $tableRows = collect($checks)->map(function (array $check) {
            return [
                $check['name'],
                $check['status'],
                mb_strimwidth((string) $check['details'], 0, 80, '…'),
                mb_strimwidth((string) $check['recommendation'], 0, 80, '…'),
            ];
        })->toArray();

        $this->table($headers, $tableRows);
        $this->newLine();

        $summaryLine = sprintf('%d OK, %d WARN, %d FAIL de %d checks',
            $summary['ok'], $summary['warn'], $summary['fail'], $summary['total']);

        if ($summary['fail'] > 0) {
            $this->error("  Resumo: {$summaryLine}");
        } elseif ($summary['warn'] > 0) {
            $this->warn("  Resumo: {$summaryLine}");
        } else {
            $this->info("  Resumo: {$summaryLine}");
        }

        $this->newLine();
        return $this->resolveExitCode($summary, $alert);
    }

    private function outputJson(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $output = [
            'timestamp'       => now()->toIso8601String(),
            'business_filter' => $businessId,
            'checks'          => collect($checks)->map(fn ($c) => [
                'name'           => $c['name'],
                'status'         => $c['status'],
                'value'          => $c['value'],
                'threshold'      => $c['threshold'],
                'details'        => $c['details'],
                'recommendation' => $c['recommendation'],
            ])->values()->toArray(),
            'summary' => $summary,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $this->resolveExitCode($summary, $alert);
    }

    private function makeCheck(string $name, string $status, mixed $value, string $threshold,
        string $details, string $recommendation): array
    {
        return compact('name', 'status', 'value', 'threshold', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) return 0;
        if ($summary['fail'] > 0) return 2;
        if ($summary['warn'] > 0) return 1;
        return 0;
    }
}
