<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpTask;

/**
 * TaskRegistry Fase 0 — Tool tasks-detail.
 *
 * Detalhe de 1 task pelo ID. Inclui description completa, dependencies,
 * source path pra navegar no git.
 */
class TasksDetailTool extends Tool
{
    protected string $name = 'tasks-detail';

    protected string $title = 'Detalhe de uma task (US-*)';

    protected string $description = 'Retorna detalhe completo de uma user story (US-NNN) — descrição, status, owner, dependencies, path do source no git. Use após tasks-list pra ver o que precisa fazer.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->required()
                ->description('Identificador da task (ex: US-NFSE-001)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $taskId = trim((string) $request->get('task_id', ''));
        if ($taskId === '') {
            return Response::error('Parâmetro "task_id" obrigatório (ex: US-NFSE-001).');
        }

        $taskId = strtoupper($taskId);

        $t = McpTask::where('task_id', $taskId)->first();
        if ($t === null) {
            return Response::text("Task `{$taskId}` não encontrada. Use `tasks-list` pra ver disponíveis.");
        }

        $output = "# {$t->task_id} — {$t->title}\n\n";
        $output .= "- **Status**: {$t->status}\n";
        $output .= "- **Owner**: " . ($t->owner ?? 'unowned') . "\n";
        $output .= "- **Module**: {$t->module}\n";
        if ($t->sprint) $output .= "- **Sprint**: {$t->sprint}\n";
        $output .= "- **Priority**: " . ($t->priority ?? 'p2') . "\n";
        if ($t->estimate_h) $output .= "- **Estimate**: ~{$t->estimate_h}h\n";
        if (! empty($t->blocked_by)) {
            $output .= "- **Blocked by**: " . implode(', ', $t->blocked_by) . "\n";
        }
        $output .= "- **Source**: `{$t->source_path}`";
        if ($t->source_git_sha) $output .= " (sha {$t->source_git_sha})";
        $output .= "\n";
        $output .= "- **Parsed**: " . $t->parsed_at?->toIso8601String() . "\n\n";

        if ($t->description) {
            $output .= "## Descrição\n\n{$t->description}\n\n";
        }

        $output .= "_Pra editar, edite o SPEC e rode `mcp:tasks:sync`._\n";

        return Response::text($output);
    }
}
