<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;

/**
 * ADR 0070 — view sistema "triage" (tasks novas sem owner/prio/cycle).
 */
class TriageTool extends Tool
{
    protected string $name = 'triage';

    protected string $title = 'Triage — tasks sem owner/prio';

    protected string $description = 'Tasks novas que precisam ser triadas: sem owner, sem priority, ou em status=backlog. Default: todos projects, ordenado por created_at desc.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (ex: COPI). Omite pra todos.'),
            'limit' => $schema->integer()->description('Máx (default 30)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = $request->get('project') ? strtoupper((string) $request->get('project')) : null;
        $limit = (int) $request->get('limit', 30);

        $projectId = null;
        if ($key) {
            $projectId = McpProject::where('key', $key)->value('id');
            if (! $projectId) {
                return Response::text("Project '{$key}' não encontrado.");
            }
        }

        $q = McpTask::triage()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderByDesc('created_at');

        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        $tasks = $q->limit($limit)->get();

        if ($tasks->isEmpty()) {
            return Response::text("🎯 Triage limpa" . ($key ? " em {$key}" : '') . " — nada precisa de atenção.");
        }

        $md = "# Triage" . ($key ? " — {$key}" : '') . "\n\n";
        $md .= "Total: **{$tasks->count()}**\n\n";

        foreach ($tasks as $t) {
            $id = $t->getDisplayIdAttribute();
            $issues = [];
            if (! $t->owner) $issues[] = '🚫 sem owner';
            if (! $t->priority) $issues[] = '❓ sem prio';
            if ($t->status === 'backlog') $issues[] = '🗂️ backlog';
            $issuesStr = implode(' · ', $issues);
            $time = $t->created_at?->diffForHumans() ?? '';
            $md .= "- **{$id}** {$t->title} _({$time})_\n  └ {$issuesStr}\n";
        }

        $md .= "\n---\n_Use `tasks-update <id> owner:NOME priority:p1` pra triar._";

        return Response::text($md);
    }
}
