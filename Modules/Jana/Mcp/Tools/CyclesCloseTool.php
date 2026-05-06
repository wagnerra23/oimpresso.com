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

/**
 * ADR 0070 — fecha cycle ativo, opcionalmente faz rollover de tasks
 * incompletas pro próximo cycle (Linear-style).
 */
class CyclesCloseTool extends Tool
{
    protected string $name = 'cycles-close';

    protected string $title = 'Fechar cycle ativo';

    protected string $description = 'Fecha o cycle ativo do projeto. Com rollover_to=KEY move tasks NOT IN (done,cancelled) pro cycle alvo. Adiciona retro JSON se passado.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (default COPI)'),
            'cycle' => $schema->string()->description('Cycle key (default: active)'),
            'rollover_to' => $schema->string()->description('Cycle key destino pra mover incompletas. Omite pra não fazer rollover.'),
            'retro_sucessos' => $schema->string()->description('Resumo sucessos do cycle (string ou JSON array)'),
            'retro_falhas' => $schema->string()->description('Resumo falhas do cycle'),
            'retro_licao' => $schema->string()->description('1 lição pro próximo cycle'),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = strtoupper((string) $request->get('project', 'COPI'));
        $project = McpProject::where('key', $key)->first();
        if (! $project) return Response::text("Project '{$key}' não encontrado.");

        $cycleKey = $request->get('cycle');
        $cycle = $cycleKey
            ? McpCycle::where('project_id', $project->id)->where('key', strtoupper((string) $cycleKey))->first()
            : McpCycle::where('project_id', $project->id)->where('status', 'active')->first();

        if (! $cycle) return Response::text("Nenhum cycle ATIVO em {$key}.");

        $rolloverTo = $request->get('rollover_to');
        $rolloverTarget = null;
        if ($rolloverTo) {
            $rolloverTarget = McpCycle::where('project_id', $project->id)
                ->where('key', strtoupper((string) $rolloverTo))
                ->first();
            if (! $rolloverTarget) {
                return Response::text("Cycle alvo '{$rolloverTo}' não existe. Crie antes via cycles-create.");
            }
        }

        $rolledOver = 0;
        $closed = 0;

        DB::transaction(function () use ($cycle, $rolloverTarget, &$rolledOver, &$closed) {
            if ($rolloverTarget) {
                $incompletas = McpTask::where('cycle_id', $cycle->id)
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->get();

                foreach ($incompletas as $t) {
                    $oldCycleId = $t->cycle_id;
                    $t->cycle_id = $rolloverTarget->id;
                    $t->save();

                    McpTaskEvent::log(
                        taskId: $t->task_id,
                        eventType: 'field_updated',
                        from: (string) $oldCycleId,
                        to: (string) $rolloverTarget->id,
                        author: 'cycles-close',
                        note: "Rollover do cycle {$cycle->key} pro {$rolloverTarget->key}",
                    );
                    $rolledOver++;
                }
            }

            $cycle->status = 'closed';

            // Retro
            $retro = $cycle->retro ?? [];
            if ($s = request()->get('retro_sucessos')) $retro['sucessos'] = $s;
            if ($f = request()->get('retro_falhas')) $retro['falhas'] = $f;
            if ($l = request()->get('retro_licao')) $retro['licao_proximo'] = $l;
            if (! empty($retro)) $cycle->retro = $retro;

            $cycle->save();
            $closed = 1;
        });

        $md = "✅ Cycle **{$cycle->key}** fechado em {$key}.\n\n";
        if ($rolloverTarget) {
            $md .= "🔄 **{$rolledOver}** tasks rolaram pro cycle **{$rolloverTarget->key}**\n\n";
        }
        $md .= "Próximo: ative o próximo cycle com `cycles-active project:{$key}` ou crie um novo com `cycles-create`.";

        return Response::text($md);
    }
}
