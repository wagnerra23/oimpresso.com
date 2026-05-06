<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ADR 0070 — substitui tasks-current. Cycle ativo + goals + tasks ativas.
 */
class CyclesActiveTool extends Tool
{
    protected string $name = 'cycles-active';

    protected string $title = 'Cycle ativo (cycle + goals + tasks)';

    protected string $description = 'Retorna o cycle ativo do projeto: goal, métricas trackadas, dias restantes, tasks ativas (status doing/review/blocked) e contadores. Substitui tasks-current (deprecated).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (ex: COPI). Default: COPI'),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = strtoupper((string) $request->get('project', 'COPI'));
        $project = McpProject::where('key', $key)->first();
        if (! $project) {
            return Response::text("Project '{$key}' não encontrado.");
        }

        $cycle = McpCycle::where('project_id', $project->id)->where('status', 'active')->first();
        if (! $cycle) {
            return Response::text("Nenhum cycle ATIVO em {$key}. Use `cycles-list project:{$key}` para ver todos.");
        }

        $goals = $cycle->goals()->get();
        $tasksActive = McpTask::where('cycle_id', $cycle->id)
            ->whereIn('status', ['doing', 'review', 'blocked'])
            ->orderBy('priority')
            ->orderBy('due_date')
            ->limit(20)
            ->get();
        $tasksByStatus = McpTask::where('cycle_id', $cycle->id)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->toArray();

        $md = "# Cycle {$cycle->key} — {$project->key}\n\n";
        $md .= "**" . ($cycle->name ?? $cycle->key) . "** · " . $cycle->start_date->toDateString() . " → " . $cycle->end_date->toDateString();
        $md .= " · _" . round($cycle->progressPercent()) . "% decorrido · " . $cycle->daysRemaining() . " dias restantes_\n\n";

        if ($cycle->goal) {
            $md .= "## 🎯 Goal\n\n> " . $cycle->goal . "\n\n";
        }

        if ($goals->isNotEmpty()) {
            $md .= "## 📊 Goals trackados\n\n";
            foreach ($goals as $g) {
                $icon = $g->status === 'done' ? '✅' : ($g->status === 'missed' ? '❌' : '🔲');
                $achieved = $g->achieved_value ? " · atual: `{$g->achieved_value}`" : '';
                $target = $g->target_value ? " · alvo: `{$g->target_value}`" : '';
                $md .= "- {$icon} {$g->description}{$target}{$achieved}\n";
            }
            $md .= "\n";
        }

        $md .= "## 📈 Status\n\n";
        foreach (['doing', 'review', 'blocked', 'todo', 'backlog', 'done', 'cancelled'] as $st) {
            if (! empty($tasksByStatus[$st])) {
                $md .= "- **{$st}**: " . $tasksByStatus[$st] . "\n";
            }
        }
        $md .= "\n";

        if ($tasksActive->isNotEmpty()) {
            $md .= "## 🔥 Tasks ativas (top 20)\n\n";
            foreach ($tasksActive as $t) {
                $id = $t->getDisplayIdAttribute();
                $due = $t->due_date ? ' · 📅 ' . $t->due_date->toDateString() : '';
                $owner = $t->owner ? " · @{$t->owner}" : '';
                $md .= "- `{$t->status}` **{$id}** {$t->title}{$owner}{$due}\n";
            }
        }

        $md .= "\n---\n_Cycle id={$cycle->id} · Tools: `tasks-list cycle:{$cycle->id}`, `cycle-goals-track cycle:{$cycle->key}`, `my-work`, `triage`._";

        return Response::text($md);
    }
}
