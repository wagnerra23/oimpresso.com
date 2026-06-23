<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * Sentinela tasks:unassigned — flagga US `status=todo` sem `cycle_id` E/OU sem `owner`.
 *
 * Por que importa (furo #2 da auditoria 2026-06-21, US-INFRA-043): toda US criada
 * por `tasks-create` nasce `todo`/`unowned`/sem-cycle. Jana filtra o roadmap por
 * `cycle_id` (e ProjectMgmt por `epic_id`) — então a US fica INVISÍVEL no roadmap
 * até alguém escrever `cycle:` na linha do SPEC.md à mão (foi o que o #3165 teve
 * que fazer nas 6 US do #3164). Nada cobrava a triagem: o `triage` é não-bloqueante
 * e afogado, e `mcp:tasks:orphans` mede a direção oposta (DB sem SPEC).
 *
 * Este comando dá VISIBILIDADE: lista as não-atribuídas (+ `--json` pro Daily Brief).
 * Advisory por default (exit 0); `--strict` vira ratchet (exit 1) quando estabilizar.
 *
 * Espelha McpTasksOrphansCommand (estrutura/teste) e o papel de sentinela do
 * `plan-health.mjs` (ADR 0294 Onda 1).
 *
 * Multi-tenant: `mcp_tasks` é project-wide (ADR 0070), sem business_id.
 */
class McpTasksUnassignedCommand extends Command
{
    protected $signature = 'mcp:tasks:unassigned
                            {--days=0 : Só flagga US criadas há mais de N dias (carência pra recém-criadas)}
                            {--module= : Limita a um módulo}
                            {--strict : Exit 1 se houver não-atribuída (vira ratchet/gate quando estabilizar)}
                            {--json : Output JSON em vez de tabela}';

    protected $description = 'Flagga US status=todo sem cycle_id e/ou sem owner — invisíveis no roadmap (furo #2 auditoria 2026-06-21, US-INFRA-043).';

    public function handle(): int
    {
        $apenasModulo = $this->option('module') ?: null;
        $minDias = (int) $this->option('days');
        $strict = (bool) $this->option('strict');

        $itens = $this->detectarNaoAtribuidas($apenasModulo, $minDias);

        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'module' => $apenasModulo,
                'min_days' => $minDias,
                'total' => count($itens),
                'sem_cycle' => count(array_filter($itens, static fn ($i) => $i['cycle_id'] === null)),
                'sem_owner' => count(array_filter($itens, static fn ($i) => $i['owner'] === null)),
                'tasks' => $itens,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($itens);
        }

        Log::channel('single')->info('mcp:tasks:unassigned', [
            'module' => $apenasModulo,
            'min_days' => $minDias,
            'total' => count($itens),
            'task_ids' => array_map(static fn ($i) => $i['task_id'], $itens),
        ]);

        // Advisory por default; só morde com --strict (acceptance US-INFRA-043:
        // "Opção de virar ratchet (exit 1) quando estabilizar").
        if ($strict) {
            return $itens === [] ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * US `status=todo` sem `cycle_id` E/OU sem `owner`, opcionalmente com idade > $minDias.
     *
     * Público pra ser testável sem console parsing (espelha
     * McpTasksOrphansCommand::detectarOrfas).
     *
     * @return list<array{task_id:string, module:string, owner:?string, cycle_id:?int, falta:string, created_at:?string, title:string}>
     */
    public function detectarNaoAtribuidas(?string $apenasModulo = null, int $minDias = 0): array
    {
        $query = McpTask::query()
            ->where('status', 'todo')
            ->where(static fn ($q) => $q->whereNull('cycle_id')->orWhereNull('owner'))
            ->when($apenasModulo !== null, fn ($q) => $q->where('module', $apenasModulo))
            ->when($minDias > 0, fn ($q) => $q->where('created_at', '<=', now()->subDays($minDias)));

        $itens = [];
        foreach ($query->get() as $task) {
            $semCycle = $task->cycle_id === null;
            $semOwner = $task->owner === null;
            $falta = match (true) {
                $semCycle && $semOwner => 'cycle+owner',
                $semCycle => 'cycle',
                default => 'owner',
            };

            $itens[] = [
                'task_id' => (string) $task->task_id,
                'module' => (string) $task->module,
                'owner' => $task->owner,
                'cycle_id' => $task->cycle_id !== null ? (int) $task->cycle_id : null,
                'falta' => $falta,
                'created_at' => optional($task->created_at)->toDateString(),
                'title' => (string) $task->title,
            ];
        }

        usort($itens, static fn ($a, $b) => strcmp($a['task_id'], $b['task_id']));

        return $itens;
    }

    /** @param list<array<string,mixed>> $itens */
    protected function renderTable(array $itens): void
    {
        if ($itens === []) {
            $this->info('✨ Nenhuma US todo sem cycle/owner — tudo visível no roadmap.');

            return;
        }

        $this->warn(count($itens) . ' US todo sem cycle/owner (invisível no roadmap):');
        $this->table(
            ['task_id', 'módulo', 'falta', 'owner', 'criada', 'título'],
            array_map(static fn ($i) => [
                $i['task_id'],
                $i['module'],
                $i['falta'],
                $i['owner'] ?? '—',
                $i['created_at'] ?? '—',
                mb_strimwidth((string) $i['title'], 0, 48, '…'),
            ], $itens),
        );
        $this->newLine();
        $this->line('Triagem: assine `cycle:` (e owner) na linha da US no SPEC.md + rode mcp:tasks:sync. '
            . 'Ex: o #3165 atribuiu `cycle: CYCLE-SAUDE` às 6 US do #3164.');
    }
}
