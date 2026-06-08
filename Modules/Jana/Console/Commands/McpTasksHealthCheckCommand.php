<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * Bug #4 (BUGS-MCP-SYNC-2026-05-13) — Staleness / inactive detection.
 *
 * Wagner tem 30 tasks "ativas" mas só 2 doing real — 6 BLOCKED dormentes
 * Trilha Gold sem update há semanas + 19 TODO acumulando. Linear/Jira
 * moderno flagga isso automático; o nosso MCP não.
 *
 * Roda 4 verificações de staleness sobre `mcp_tasks`:
 *   - `todo` sem update há >21d → flag stale_todo (sugere priorizar/cancelar)
 *   - `blocked` há >30d → flag stale_blocked (propõe cancelar)
 *   - `doing` sem commit linkado (mcp_git_links) há >7d → flag stale_doing
 *   - `review` sem update há >5d → flag stale_review (cobrar revisor)
 *
 * Multi-tenant: `mcp_tasks` é project-wide (cache da SPEC.md canônica em
 * memory/requisitos/), SEM coluna business_id. ADR 0070 / TaskRegistry.
 * O flag --business é aceito como no-op pra forward-compat caso a tabela
 * receba business_id no futuro (ADR 0093 IRREVOGÁVEL, mas atualmente
 * fora de escopo aqui).
 *
 * Saída padrão: tabela markdown no stdout + log estruturado.
 *
 * Schedule: app/Console/Kernel.php — daily 06:00 BRT (mesma janela do
 * jana:health-check), sem --auto-comment (só relatório).
 *
 * Tool MCP equivalente: tasks-health (TasksHealthTool) — expõe o mesmo
 * pipeline pra Claude rodar sob demanda.
 */
class McpTasksHealthCheckCommand extends Command
{
    protected $signature = 'mcp:tasks:health-check
                            {--auto-comment : Adiciona comentário automático nas tasks flagadas via TaskCrudService::comment()}
                            {--owner= : Filtra por owner (ex: wagner). Default: todos.}
                            {--business= : No-op — mcp_tasks é project-wide (ADR 0070). Reservado pra forward-compat.}
                            {--json : Output JSON em vez de tabela markdown}';

    protected $description = 'Detecta tasks "dormentes" (stale) em mcp_tasks e opcionalmente comenta sugestão automática. ADR 0070 + Bug #4 BUGS-MCP-SYNC-2026-05-13.';

    /** Thresholds em dias por status — single source of truth. */
    public const THRESHOLDS = [
        'todo'    => 21,
        'blocked' => 30,
        'doing'   => 7,   // sem commit linkado em mcp_git_links
        'review'  => 5,
    ];

    public function handle(): int
    {
        $autoComment = (bool) $this->option('auto-comment');
        $owner = $this->option('owner') ? strtolower((string) $this->option('owner')) : null;
        $asJson = (bool) $this->option('json');

        $flagged = $this->scanStaleness($owner);

        if ($autoComment && $flagged->isNotEmpty()) {
            $this->postAutoComments($flagged);
        }

        if ($asJson) {
            $this->line(json_encode([
                'checked_at' => now()->toIso8601String(),
                'thresholds_days' => self::THRESHOLDS,
                'owner' => $owner,
                'auto_comment' => $autoComment,
                'total_flagged' => $flagged->count(),
                'tasks' => $flagged->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($this->renderMarkdown($flagged, $owner, $autoComment));
        }

        // Log estruturado pra Kibana/Datadog/grep
        Log::channel('single')->info('mcp:tasks:health-check', [
            'owner' => $owner,
            'auto_comment' => $autoComment,
            'flagged_count' => $flagged->count(),
            'flags_breakdown' => $flagged->groupBy('flag')->map->count()->toArray(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Varre mcp_tasks e devolve uma Collection de arrays com:
     *   task_id, identifier, status, owner, updated_at, days_stale, flag, suggestion
     *
     * Pula tasks fechadas (done/cancelled) — staleness só importa em ativas.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function scanStaleness(?string $owner = null): Collection
    {
        $now = now();
        $query = McpTask::query()
            ->whereIn('status', ['todo', 'blocked', 'doing', 'review']);

        if ($owner) {
            $query->where('owner', $owner);
        }

        $flagged = collect();

        foreach ($query->get() as $task) {
            $flag = $this->classify($task, $now);
            if ($flag === null) {
                continue;
            }

            $daysStale = $this->daysSince($task, $now);

            $flagged->push([
                'task_id'    => $task->task_id,
                'identifier' => $task->getDisplayIdAttribute(),
                'status'     => $task->status,
                'owner'      => $task->owner,
                'updated_at' => optional($task->updated_at)->toDateString() ?? '—',
                'days_stale' => $daysStale,
                'flag'       => $flag,
                'suggestion' => $this->suggestionFor($flag, $daysStale),
            ]);
        }

        // Mais stale primeiro (maior days_stale)
        return $flagged->sortByDesc('days_stale')->values();
    }

    /**
     * Decide se a task está stale pra seu status. Retorna a flag ou null.
     *
     * Regra `doing`: exige >7d sem commit linkado em mcp_git_links — ausência
     * de commit é o sinal de stale, não só updated_at.
     */
    protected function classify(McpTask $task, Carbon $now): ?string
    {
        $daysSinceUpdate = $this->daysSince($task, $now);

        switch ($task->status) {
            case 'todo':
                return $daysSinceUpdate > self::THRESHOLDS['todo'] ? 'stale_todo' : null;

            case 'blocked':
                return $daysSinceUpdate > self::THRESHOLDS['blocked'] ? 'stale_blocked' : null;

            case 'review':
                return $daysSinceUpdate > self::THRESHOLDS['review'] ? 'stale_review' : null;

            case 'doing':
                // Defesa: se tabela mcp_git_links não existe (ambiente fresh), trata como sem-commit.
                if (! Schema::hasTable('mcp_git_links')) {
                    return $daysSinceUpdate > self::THRESHOLDS['doing'] ? 'stale_doing' : null;
                }
                $latestCommit = DB::table('mcp_git_links')
                    ->where('task_id', $task->task_id)
                    ->whereNotNull('commit_sha')
                    ->max('occurred_at');

                if ($latestCommit === null) {
                    // Nunca commitou — fala stale se passou do limiar via updated_at
                    return $daysSinceUpdate > self::THRESHOLDS['doing'] ? 'stale_doing' : null;
                }

                $daysSinceCommit = (int) abs(Carbon::parse($latestCommit)->diffInDays($now));
                return $daysSinceCommit > self::THRESHOLDS['doing'] ? 'stale_doing' : null;
        }

        return null;
    }

    protected function daysSince(McpTask $task, Carbon $now): int
    {
        $ref = $task->updated_at ?? $task->created_at;
        if (! $ref) {
            return PHP_INT_MAX;
        }
        // Carbon ^3 retorna float; trunca pra int pra comparações com thresholds
        return (int) abs($ref->diffInDays($now));
    }

    protected function suggestionFor(string $flag, int $days): string
    {
        return match ($flag) {
            'stale_todo'    => "Sem update há {$days}d. Priorizar ou cancelar?",
            'stale_blocked' => "Bloqueada há {$days}d. Cancelar (cleanup) ou desbloquear com plano?",
            'stale_doing'   => "Em doing há {$days}d sem commit linkado. Reassign, quebrar em subtasks, ou voltar pra todo?",
            'stale_review'  => "Em review há {$days}d. Cobrar revisor ou reassign.",
            default         => "Stale. Investigar.",
        };
    }

    /**
     * Adiciona comentário automático nas tasks flagged via TaskCrudService.
     * Usa autor "mcp-health-bot" pra ficar identificável na timeline.
     */
    protected function postAutoComments(Collection $flagged): void
    {
        $svc = app(TaskCrudService::class);
        $author = 'mcp-health-bot';

        foreach ($flagged as $row) {
            try {
                $svc->comment(
                    $row['task_id'],
                    "🤖 **Health check automático** ({$row['flag']}) — {$row['suggestion']}",
                    $author,
                );
                $this->line("· comentário em <fg=cyan>{$row['identifier']}</> ({$row['flag']})");
            } catch (\Throwable $e) {
                $this->warn("· falha em {$row['identifier']}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Tabela markdown — formato igual ao Tool MCP TasksHealthTool consome.
     */
    public function renderMarkdown(Collection $flagged, ?string $owner, bool $autoComment): string
    {
        if ($flagged->isEmpty()) {
            $scope = $owner ? " pra @{$owner}" : '';
            return "✨ Nenhuma task stale{$scope}. Backlog saudável.\n";
        }

        $md = "# Tasks staleness — health check MCP\n\n";
        $md .= "_Checado em " . now()->toDateTimeString() . " BRT_\n";
        $md .= "_Owner filter: " . ($owner ? "@{$owner}" : 'todos') . "_\n";
        if ($autoComment) {
            $md .= "_Auto-comment: **ATIVADO** (comentários postados nas tasks)_\n";
        }
        $md .= "_Thresholds: todo>21d · blocked>30d · doing>7d sem commit · review>5d_\n\n";

        $md .= "Total flagged: **{$flagged->count()}**\n\n";

        // Breakdown por flag
        $byFlag = $flagged->groupBy('flag');
        foreach (['stale_doing', 'stale_review', 'stale_blocked', 'stale_todo'] as $flag) {
            $items = $byFlag->get($flag);
            if (! $items || $items->isEmpty()) {
                continue;
            }
            $emoji = match ($flag) {
                'stale_doing'    => '🔥',
                'stale_review'   => '👀',
                'stale_blocked'  => '⛔',
                'stale_todo'     => '📋',
                default          => '·',
            };
            $md .= "## {$emoji} " . strtoupper($flag) . " ({$items->count()})\n\n";
            $md .= "| task_id | status | última_update | flag | sugestão |\n";
            $md .= "|---|---|---|---|---|\n";
            foreach ($items as $row) {
                $owner = $row['owner'] ? "@{$row['owner']}" : '—';
                $md .= sprintf(
                    "| **%s** | %s | %s (%dd) | `%s` | %s |\n",
                    $row['identifier'],
                    $row['status'],
                    $row['updated_at'],
                    $row['days_stale'],
                    $row['flag'],
                    $row['suggestion'],
                );
                $md .= "| _owner: {$owner}_ | | | | |\n";
            }
            $md .= "\n";
        }

        $md .= "---\n";
        $md .= "_Use `tasks-update <id> status:cancelled` pra cleanup ou `tasks-comment <id>` pra cobrar update._\n";
        if (! $autoComment) {
            $md .= "_Re-rode com `--auto-comment` pra postar comentário automático nas flagged._\n";
        }

        return $md;
    }
}
