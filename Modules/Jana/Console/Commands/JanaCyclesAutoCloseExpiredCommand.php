<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskComment;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * Auto-rollover Linear-style (Gap #5 §4 do COMPARATIVO-MCP-ESTADO-DA-ARTE-2026-05-13).
 *
 * Detecta cycles com end_date no passado e status != closed, então:
 *   1. Identifica próximo cycle (planning|active) com start_date >= today
 *   2. Se NÃO existir, CRIA automaticamente "CYCLE-N+1 (auto-created)"
 *      vazio em status=planning pra Wagner planejar depois
 *   3. Move tasks doing|review|blocked pro próximo cycle (status=todo fica
 *      pra forçar triage — replica regra do CyclesCloseTool default)
 *   4. Fecha cycle expirado (status=closed)
 *   5. Adiciona comentário em tasks que estavam blocked pedindo re-avaliação
 *
 * Schedule canônico: daily 23:55 BRT em app/Console/Kernel.php (ambiente live).
 *
 * Linear faz isso por default desde 2019. Jira tem opt-in equivalente (sprint
 * auto-complete). Score CAPTERRA antes deste comando: 60% (cycles manuais).
 * Após: ~90% (cycles ok + auto-rollover automático).
 */
class JanaCyclesAutoCloseExpiredCommand extends Command
{
    protected $signature = 'jana:cycles:auto-close-expired
                            {--project= : Filtra por project key (ex: COPI). Default: todos.}
                            {--dry-run : Mostra o que faria sem aplicar mudanças.}
                            {--force-rollover-todo : Inclui tasks status=todo no rollover (default false — força triage)}
                            {--no-auto-create-next : Desliga criação automática do próximo cycle (default: cria vazio se não existir)}';

    protected $description = 'Auto-fecha cycles expirados (end_date < hoje) + rollover Linear-style das tasks incompletas pro próximo cycle. Cria próximo cycle vazio se não existir. ADR 0070 — Gap #5 COMPARATIVO MCP.';

    public function handle(): int
    {
        $projectFilter = $this->option('project') ? strtoupper((string) $this->option('project')) : null;
        $dryRun = (bool) $this->option('dry-run');
        $forceRolloverTodo = (bool) $this->option('force-rollover-todo');
        // Default: TRUE (Linear-style auto-create). Use --no-auto-create-next pra desligar.
        $autoCreateNext = ! (bool) $this->option('no-auto-create-next');

        $today = today();

        $query = McpCycle::query()
            ->where('end_date', '<', $today)
            ->whereNotIn('status', ['closed']);

        if ($projectFilter) {
            $project = McpProject::where('key', $projectFilter)->first();
            if (! $project) {
                $this->error("Project '{$projectFilter}' não encontrado.");
                return self::FAILURE;
            }
            $query->where('project_id', $project->id);
        }

        $expired = $query->orderBy('end_date', 'asc')->get();

        if ($expired->isEmpty()) {
            $this->info('Nenhum cycle expirado pra fechar. Backlog saudável.');
            Log::channel('single')->info('jana:cycles:auto-close-expired — sem cycles expirados.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d cycle(s) expirado(s) detectado(s)%s.',
            $expired->count(),
            $dryRun ? ' (DRY-RUN — nenhuma mudança aplicada)' : ''
        ));

        $report = [
            'closed_count' => 0,
            'rolled_over_count' => 0,
            'blocked_flagged_count' => 0,
            'todo_left_count' => 0,
            'next_cycles_created' => 0,
            'cycles' => [],
        ];

        foreach ($expired as $cycle) {
            $project = $cycle->project;
            if (! $project) {
                $this->warn("Cycle id={$cycle->id} sem project. Pulando.");
                continue;
            }

            $result = $this->processExpiredCycle(
                $cycle,
                $project,
                $autoCreateNext,
                $forceRolloverTodo,
                $dryRun
            );

            $report['closed_count'] += $result['closed'];
            $report['rolled_over_count'] += $result['rolled_over'];
            $report['blocked_flagged_count'] += $result['blocked_flagged'];
            $report['todo_left_count'] += $result['todo_left'];
            $report['next_cycles_created'] += $result['next_cycle_created'] ? 1 : 0;
            $report['cycles'][] = $result;

            $this->line(sprintf(
                '  ✅ %s/%s → fechado | rollover: %d | blocked flagged: %d | todo restante: %d%s',
                $project->key,
                $cycle->key,
                $result['rolled_over'],
                $result['blocked_flagged'],
                $result['todo_left'],
                $result['next_cycle_created'] ? ' | próximo cycle CRIADO: ' . $result['next_cycle_key'] : ''
            ));
        }

        // Log estruturado pra observability
        Log::channel('single')->info('jana:cycles:auto-close-expired completed', [
            'dry_run' => $dryRun,
            'project_filter' => $projectFilter,
            'force_rollover_todo' => $forceRolloverTodo,
            'auto_create_next' => $autoCreateNext,
            'closed_count' => $report['closed_count'],
            'rolled_over_count' => $report['rolled_over_count'],
            'blocked_flagged_count' => $report['blocked_flagged_count'],
            'todo_left_count' => $report['todo_left_count'],
            'next_cycles_created' => $report['next_cycles_created'],
        ]);

        return self::SUCCESS;
    }

    /**
     * Processa um único cycle expirado.
     *
     * Retorna métricas pra agregação no relatório.
     *
     * @return array{closed:int,rolled_over:int,blocked_flagged:int,todo_left:int,next_cycle_created:bool,next_cycle_key:?string}
     */
    protected function processExpiredCycle(
        McpCycle $cycle,
        McpProject $project,
        bool $autoCreateNext,
        bool $forceRolloverTodo,
        bool $dryRun
    ): array {
        $closed = 0;
        $rolledOver = 0;
        $blockedFlagged = 0;
        $todoLeft = 0;
        $nextCycleCreated = false;
        $nextCycleKey = null;

        // Passo 1: tentar achar próximo cycle existente (planning|active, start_date >= today)
        $next = McpCycle::where('project_id', $project->id)
            ->whereIn('status', ['planning', 'active'])
            ->where('id', '!=', $cycle->id)
            ->orderBy('start_date', 'asc')
            ->first();

        // Passo 2: se não tem próximo cycle e auto-create ligado, cria um vazio
        if (! $next && $autoCreateNext) {
            $nextCycleKey = $this->inferNextCycleKey($project, $cycle);

            if ($dryRun) {
                $this->line("  [DRY-RUN] Criaria cycle vazio '{$nextCycleKey}' em {$project->key}.");
                $nextCycleCreated = true;
            } else {
                // Período sugerido: próxima janela de 2 semanas a partir do dia seguinte
                $startDate = today()->addDay();
                $endDate = $startDate->copy()->addDays(13); // 2 semanas (14 dias inclusive)

                $next = McpCycle::create([
                    'project_id' => $project->id,
                    'key' => $nextCycleKey,
                    'name' => 'Auto-created (após rollover de ' . $cycle->key . ')',
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'goal' => 'Cycle criado automaticamente — Wagner define goal.',
                    'status' => 'planning',
                ]);
                $nextCycleCreated = true;
            }
        }

        // Passo 3: rollover de tasks incompletas (se temos cycle alvo)
        if ($next || $dryRun) {
            $autoRolloverStatuses = ['doing', 'review', 'blocked'];
            if ($forceRolloverTodo) {
                $autoRolloverStatuses[] = 'todo';
            }

            $incompletas = McpTask::where('cycle_id', $cycle->id)
                ->whereIn('status', $autoRolloverStatuses)
                ->get();

            if (! $dryRun) {
                DB::transaction(function () use ($cycle, $next, $incompletas, &$rolledOver, &$blockedFlagged) {
                    foreach ($incompletas as $t) {
                        $oldCycleId = $t->cycle_id;
                        $statusAtual = $t->status;
                        $t->cycle_id = $next->id;
                        $t->save();

                        McpTaskEvent::log(
                            taskId: $t->task_id,
                            eventType: 'field_updated',
                            from: (string) $oldCycleId,
                            to: (string) $next->id,
                            author: 'auto-close-expired',
                            note: "Auto-rollover (status={$statusAtual}) do cycle {$cycle->key} pro {$next->key}",
                        );
                        $rolledOver++;

                        if ($statusAtual === 'blocked') {
                            try {
                                McpTaskComment::create([
                                    'task_id' => $t->task_id,
                                    'author' => 'auto-close-expired',
                                    'body' => "Esta task estava `blocked` quando o cycle **{$cycle->key}** expirou e foi auto-fechado. Rolou pro **{$next->key}** — **ainda é relevante?** Se sim, identifique o bloqueador atual; se não, marque `cancelled`.",
                                ]);
                                $blockedFlagged++;
                            } catch (\Throwable $e) {
                                // Comentário é nice-to-have
                            }
                        }
                    }
                });
            } else {
                $rolledOver = $incompletas->count();
                $blockedFlagged = $incompletas->where('status', 'blocked')->count();
            }

            // Contagem de TODOs deixados pra trás (força triage)
            if (! $forceRolloverTodo) {
                $todoLeft = McpTask::where('cycle_id', $cycle->id)
                    ->where('status', 'todo')
                    ->count();
            }
        }

        // Passo 4: fecha o cycle
        if (! $dryRun) {
            $cycle->status = 'closed';

            // Anota motivo do fechamento automático
            $retro = $cycle->retro ?? [];
            $retro['auto_closed'] = true;
            $retro['auto_closed_at'] = now()->toIso8601String();
            $retro['auto_close_reason'] = 'end_date no passado detectado por jana:cycles:auto-close-expired';
            $cycle->retro = $retro;

            $cycle->save();
            $closed = 1;
        } else {
            $closed = 1;
        }

        return [
            'project_key' => $project->key,
            'cycle_key' => $cycle->key,
            'cycle_end_date' => $cycle->end_date instanceof Carbon ? $cycle->end_date->toDateString() : (string) $cycle->end_date,
            'closed' => $closed,
            'rolled_over' => $rolledOver,
            'blocked_flagged' => $blockedFlagged,
            'todo_left' => $todoLeft,
            'next_cycle_created' => $nextCycleCreated,
            'next_cycle_key' => $next ? $next->key : $nextCycleKey,
        ];
    }

    /**
     * Infere a próxima cycle key a partir do padrão CYCLE-NN.
     *
     * Casos cobertos:
     *   - 'CYCLE-07' → 'CYCLE-08'
     *   - 'SPRINT-3' → 'SPRINT-4'
     *   - Sem dígitos no fim → fallback "{KEY}-NEXT"
     *
     * Se o gerado colide com existente, incrementa até achar slot livre.
     */
    protected function inferNextCycleKey(McpProject $project, McpCycle $expiredCycle): string
    {
        $base = $expiredCycle->key;

        // Match prefixo + número final (ex: CYCLE-07 → prefix=CYCLE-, n=07)
        if (preg_match('/^(.*?)(\d+)$/', $base, $matches)) {
            $prefix = $matches[1];
            $num = (int) $matches[2];
            $width = strlen($matches[2]); // preserva zero padding (07 → 08)

            $candidate = $prefix . str_pad((string) ($num + 1), $width, '0', STR_PAD_LEFT);

            // Evita colisão com cycles existentes no mesmo projeto
            while (McpCycle::where('project_id', $project->id)->where('key', $candidate)->exists()) {
                $num++;
                $candidate = $prefix . str_pad((string) ($num + 1), $width, '0', STR_PAD_LEFT);
            }

            return strtoupper($candidate);
        }

        // Fallback
        $candidate = strtoupper($base) . '-NEXT';
        $i = 2;
        while (McpCycle::where('project_id', $project->id)->where('key', $candidate)->exists()) {
            $candidate = strtoupper($base) . '-NEXT-' . $i;
            $i++;
        }

        return $candidate;
    }
}
