<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpCycleGoal;
use Modules\Jana\Entities\Mcp\McpProject;

/**
 * ADR 0070 — atualiza achieved_value de um goal de cycle.
 *
 * Modo read-only se sem `goal_id` + `achieved_value` — lista goals.
 */
class CycleGoalsTrackTool extends Tool
{
    protected string $name = 'cycle-goals-track';

    protected string $title = 'Trackear goal de cycle';

    protected string $description = 'Lista goals de um cycle, ou atualiza achieved_value/status de 1 goal específico (passe goal_id + achieved_value).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (default COPI)'),
            'cycle' => $schema->string()->description('Cycle key (default: active). Ex: CYCLE-01'),
            'goal_id' => $schema->integer()->description('ID do goal pra atualizar (opcional)'),
            'achieved_value' => $schema->string()->description('Novo valor (string)'),
            'status' => $schema->string()->description('open|done|missed (opcional)'),
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

        if (! $cycle) return Response::text("Cycle não encontrado em {$key}.");

        $goalId = $request->get('goal_id');

        // Modo update
        if ($goalId) {
            $goal = McpCycleGoal::where('id', (int) $goalId)->where('cycle_id', $cycle->id)->first();
            if (! $goal) return Response::text("Goal id={$goalId} não pertence ao cycle {$cycle->key}.");

            $achieved = $request->get('achieved_value');
            $status = $request->get('status');

            if ($achieved !== null) $goal->achieved_value = (string) $achieved;
            if ($status && in_array($status, McpCycleGoal::STATUSES, true)) $goal->status = $status;
            $goal->save();

            return Response::text("✅ Goal #{$goal->id} atualizado: status={$goal->status}, achieved={$goal->achieved_value}");
        }

        // Modo lista
        $goals = $cycle->goals;
        if ($goals->isEmpty()) {
            return Response::text("Cycle {$cycle->key} sem goals. Adicione via tools cycle-goals-add (TODO).");
        }

        $md = "# Goals — Cycle {$cycle->key} ({$key})\n\n";
        foreach ($goals as $g) {
            $icon = $g->status === 'done' ? '✅' : ($g->status === 'missed' ? '❌' : '🔲');
            $achieved = $g->achieved_value ? " · atual: `{$g->achieved_value}`" : '';
            $target = $g->target_value ? " · alvo: `{$g->target_value}`" : '';
            $md .= "- {$icon} **#{$g->id}** {$g->description}{$target}{$achieved}\n";
        }
        $md .= "\n---\n_Atualize com: `cycle-goals-track goal_id:N achieved_value:\"...\" status:done`_";

        return Response::text($md);
    }
}
