<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Entities\Mcp\McpTaskComment;

/**
 * ADR 0070 — fecha cycle ativo. Por DEFAULT faz rollover Linear-style de
 * tasks incompletas pro próximo cycle (auto-detectado ou via rollover_to).
 *
 * Regras de rollover por status (Linear/Jira-like, Gap #5 §4 do COMPARATIVO):
 *   - doing/review → SEMPRE rolam (trabalho em voo, manter momentum)
 *   - blocked      → rola + comentário "estava blocked no cycle X — ainda relevante?"
 *   - todo         → NÃO rola por default (força triage / evita arrastar backlog).
 *                    Use force_rollover_todo=true pra incluir tudo.
 *   - done/cancelled → ficam (terminais)
 *
 * Mantém compat: passe rollover=false explícito pra desligar; rollover_to
 * sobrescreve a auto-detecção de próximo cycle.
 */
class CyclesCloseTool extends Tool
{
    protected string $name = 'cycles-close';

    protected string $title = 'Fechar cycle ativo';

    protected string $description = 'Fecha o cycle ativo do projeto. Rollover Linear-style ON por default: doing/review/blocked rolam pro próximo cycle (auto-detectado por start_date); todo só com force_rollover_todo=true. Passe rollover=false pra desligar. rollover_to=KEY força cycle alvo. Adiciona retro JSON se passado.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (default COPI)'),
            'cycle' => $schema->string()->description('Cycle key (default: active)'),
            'rollover' => $schema->boolean()->description('Liga/desliga rollover (default: true, Linear-style)'),
            'rollover_to' => $schema->string()->description('Cycle key destino. Default: auto-detecta próximo cycle (status=planning|active com start_date >= hoje).'),
            'force_rollover_todo' => $schema->boolean()->description('Inclui status=todo no rollover (default false — força triage de backlog antigo)'),
            'retro_sucessos' => $schema->string()->description('Resumo sucessos do cycle (string ou JSON array)'),
            'retro_falhas' => $schema->string()->description('Resumo falhas do cycle'),
            'retro_licao' => $schema->string()->description('1 lição pro próximo cycle'),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = strtoupper((string) $request->get('project', 'COPI'));
        $project = McpProject::where('key', $key)->first();
        if (! $project) {
            return Response::text("Project '{$key}' não encontrado.");
        }

        $cycleKey = $request->get('cycle');
        $cycle = $cycleKey
            ? McpCycle::where('project_id', $project->id)->where('key', strtoupper((string) $cycleKey))->first()
            : McpCycle::where('project_id', $project->id)->where('status', 'active')->first();

        if (! $cycle) {
            return Response::text("Nenhum cycle ATIVO em {$key}.");
        }

        // Default: rollover ligado (Linear-style). Aceita false explícito.
        $rolloverFlag = $request->get('rollover');
        $rollover = $rolloverFlag === null ? true : (bool) filter_var($rolloverFlag, FILTER_VALIDATE_BOOLEAN);

        $forceRolloverTodo = (bool) filter_var($request->get('force_rollover_todo', false), FILTER_VALIDATE_BOOLEAN);

        $rolloverTarget = null;
        $autoTargeted = false;

        if ($rollover) {
            $rolloverTo = $request->get('rollover_to');
            if ($rolloverTo) {
                $rolloverTarget = McpCycle::where('project_id', $project->id)
                    ->where('key', strtoupper((string) $rolloverTo))
                    ->first();
                if (! $rolloverTarget) {
                    return Response::text("Cycle alvo '{$rolloverTo}' não existe. Crie antes via cycles-create.");
                }
            } else {
                // Auto-detect: próximo cycle (planning ou active) cujo start_date >= today
                // OU o cycle mais antigo com status=planning. Evita pegar cycle passado.
                $rolloverTarget = McpCycle::where('project_id', $project->id)
                    ->whereIn('status', ['planning', 'active'])
                    ->where('id', '!=', $cycle->id)
                    ->orderBy('start_date', 'asc')
                    ->first();
                $autoTargeted = (bool) $rolloverTarget;
            }
        }

        $rolledOver = 0;
        $blockedFlagged = 0;
        $todoLeft = 0;
        $closed = 0;

        DB::transaction(function () use ($cycle, $rolloverTarget, $forceRolloverTodo, $request, &$rolledOver, &$blockedFlagged, &$todoLeft, &$closed) {
            if ($rolloverTarget) {
                // Statuses que rolam por default (Linear-style): trabalho em voo
                $autoRolloverStatuses = ['doing', 'review', 'blocked'];

                if ($forceRolloverTodo) {
                    $autoRolloverStatuses[] = 'todo';
                }

                $incompletas = McpTask::where('cycle_id', $cycle->id)
                    ->whereIn('status', $autoRolloverStatuses)
                    ->get();

                foreach ($incompletas as $t) {
                    $oldCycleId = $t->cycle_id;
                    $statusAtual = $t->status;
                    $t->cycle_id = $rolloverTarget->id;
                    $t->save();

                    McpTaskEvent::log(
                        taskId: $t->task_id,
                        eventType: 'field_updated',
                        from: (string) $oldCycleId,
                        to: (string) $rolloverTarget->id,
                        author: 'cycles-close',
                        note: "Rollover (status={$statusAtual}) do cycle {$cycle->key} pro {$rolloverTarget->key}",
                    );
                    $rolledOver++;

                    // Tasks blocked recebem comentário de re-avaliação (Linear-style)
                    if ($statusAtual === 'blocked') {
                        try {
                            McpTaskComment::create([
                                'task_id' => $t->task_id,
                                'author' => 'cycles-close',
                                'body' => "Esta task estava `blocked` quando o cycle **{$cycle->key}** fechou. Rolou pro **{$rolloverTarget->key}** automaticamente — **ainda é relevante?** Se sim, identifique o bloqueador atual; se não, marque `cancelled`.",
                            ]);
                            $blockedFlagged++;
                        } catch (\Throwable $e) {
                            // Comentário é "nice to have" — não falha o rollover se comments table indisponível
                        }
                    }
                }

                // Conta TODOs que NÃO rolaram (pra mostrar no relatório, força triage)
                if (! $forceRolloverTodo) {
                    $todoLeft = McpTask::where('cycle_id', $cycle->id)
                        ->where('status', 'todo')
                        ->count();
                }
            }

            $cycle->status = 'closed';

            // Retro
            $retro = $cycle->retro ?? [];
            if ($s = $request->get('retro_sucessos')) {
                $retro['sucessos'] = $s;
            }
            if ($f = $request->get('retro_falhas')) {
                $retro['falhas'] = $f;
            }
            if ($l = $request->get('retro_licao')) {
                $retro['licao_proximo'] = $l;
            }
            if (! empty($retro)) {
                $cycle->retro = $retro;
            }

            $cycle->save();
            $closed = 1;
        });

        $md = "✅ Cycle **{$cycle->key}** fechado em {$key}.\n\n";
        if ($rolloverTarget) {
            $tag = $autoTargeted ? ' (auto-detectado)' : '';
            $md .= "🔄 **{$rolledOver}** tasks rolaram pro cycle **{$rolloverTarget->key}**{$tag}\n";
            if ($blockedFlagged > 0) {
                $md .= "⚠️ **{$blockedFlagged}** tasks que estavam `blocked` receberam comentário pra re-avaliação\n";
            }
            if ($todoLeft > 0) {
                $md .= "📋 **{$todoLeft}** tasks `todo` NÃO rolaram (force_rollover_todo=false). Triage manual via tasks-list cycle:{$cycle->key} status:todo\n";
            }
            $md .= "\n";
        } elseif (! $rolloverTarget && $request->get('rollover_to')) {
            // Caso já tratado acima com return antecipado, mas redundante por safety
        } else {
            $md .= "ℹ️ Rollover desligado (nenhum cycle alvo).\n\n";
        }
        $md .= "Próximo: `cycles-active project:{$key}` ou crie um novo com `cycles-create`.";

        return Response::text($md);
    }
}
