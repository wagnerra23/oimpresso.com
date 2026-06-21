<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 1 (US-TR-005) — Tool tasks-comment.
 *
 * Adiciona comentário DB-only a uma US-*.
 * Comentários aparecem na timeline (tasks-detail) mas NÃO no SPEC.md.
 */
class TasksCommentTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'tasks-comment';

    protected string $title = 'Comentar numa task';

    protected string $description = 'Adiciona um comentário DB-only a uma US-*. Aparece na timeline de tasks-detail. Use pra registrar progresso, decisões, blockers — sem precisar editar o SPEC.md.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('ID da task, ex: US-NFSE-001')
                ->required(),
            'comment' => $schema->string()
                ->description('Texto do comentário (markdown permitido)')
                ->required(),
            'author' => $schema->string()
                ->description('Quem está comentando (ex: eliana, wagner). Default: wagner.'),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.tasks.write')) {
            return $deny;
        }

        $taskId  = trim((string) $request->get('task_id', ''));
        $comment = trim((string) $request->get('comment', ''));
        $author  = trim((string) $request->get('author', 'wagner')) ?: 'wagner';

        if ($taskId === '') {
            return Response::text('❌ task_id é obrigatório.');
        }
        if ($comment === '') {
            return Response::text('❌ comment é obrigatório.');
        }

        try {
            $c = app(TaskCrudService::class)->comment($taskId, $comment, $author);
        } catch (\Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        $preview = mb_strlen($comment) > 80 ? mb_substr($comment, 0, 77) . '...' : $comment;

        return Response::text(
            "✅ Comentário adicionado em **{$c->task_id}** por {$author}:\n\n" .
            "> {$preview}\n\n" .
            "_Use `tasks-detail task_id={$c->task_id}` pra ver a timeline completa._"
        );
    }
}
