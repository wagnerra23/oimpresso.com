<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpTask;
use Modules\Copiloto\Entities\Mcp\McpTaskComment;
use Modules\Copiloto\Entities\Mcp\McpTaskEvent;

/**
 * TaskRegistry Fase 0/1 — Tool tasks-detail.
 *
 * Detalhe de 1 task pelo ID. Inclui description completa, dependencies,
 * source path pra navegar no git. F1: também mostra timeline de eventos
 * e comentários.
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

        // F1: timeline de eventos + comentários
        $events   = McpTaskEvent::where('task_id', $t->task_id)
            ->orderBy('occurred_at')
            ->get();

        $comments = McpTaskComment::where('task_id', $t->task_id)
            ->orderBy('created_at')
            ->get();

        if ($events->isNotEmpty() || $comments->isNotEmpty()) {
            $output .= "## Timeline\n\n";

            // Merge events + comments por data
            $timeline = collect();
            foreach ($events as $ev) {
                $timeline->push(['at' => $ev->occurred_at, 'type' => 'event', 'obj' => $ev]);
            }
            foreach ($comments as $c) {
                $timeline->push(['at' => $c->created_at, 'type' => 'comment', 'obj' => $c]);
            }
            $timeline = $timeline->sortBy('at');

            foreach ($timeline as $item) {
                $ts = $item['at']?->format('Y-m-d H:i');
                if ($item['type'] === 'event') {
                    $ev = $item['obj'];
                    $desc = match ($ev->event_type) {
                        'status_changed' => "status: `{$ev->from_value}` → `{$ev->to_value}`",
                        'assigned'       => "owner: `{$ev->from_value}` → `{$ev->to_value}`",
                        'commented'      => "comentou",
                        'created'        => "task criada",
                        'cancelled'      => "task cancelada (sumiu do SPEC)",
                        default          => "{$ev->event_type}: `{$ev->from_value}` → `{$ev->to_value}`",
                    };
                    $author = $ev->author ? " por {$ev->author}" : '';
                    $output .= "- `{$ts}` — {$desc}{$author}\n";
                } else {
                    $c = $item['obj'];
                    $preview = mb_strlen($c->body) > 100 ? mb_substr($c->body, 0, 97) . '…' : $c->body;
                    $output .= "- `{$ts}` **{$c->author}**: {$preview}\n";
                }
            }
            $output .= "\n";
        }

        $output .= "_Pra editar, edite o SPEC e rode `mcp:tasks:sync`. Pra comentar: `tasks-comment task_id={$t->task_id}`._\n";

        return Response::text($output);
    }
}
