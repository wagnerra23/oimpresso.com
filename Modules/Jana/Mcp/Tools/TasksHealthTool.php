<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Console\Commands\McpTasksHealthCheckCommand;

/**
 * Bug #4 (BUGS-MCP-SYNC-2026-05-13) — tool MCP `tasks-health`.
 *
 * Expõe o mesmo pipeline de staleness do command `mcp:tasks:health-check`
 * (Modules\Jana\Console\Commands\McpTasksHealthCheckCommand) pra Claude
 * chamar sob demanda — útil quando Wagner pergunta "que tasks estão
 * dormentes hoje?" sem precisar rodar artisan.
 *
 * Saída idêntica à tabela markdown do command (mesma função renderMarkdown).
 *
 * Schedule daily roda o command direto (sem MCP); este tool é o entry-point
 * humano via Claude Code.
 */
class TasksHealthTool extends Tool
{
    protected string $name = 'tasks-health';

    protected string $title = 'Tasks staleness — health check';

    protected string $description = 'Lista tasks ativas com flags de staleness (stale_todo >21d, stale_blocked >30d, stale_doing >7d sem commit, stale_review >5d). Use pra cleanup periódico do backlog — propõe cancelar dormentes ou cobrar update.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner' => $schema->string()
                ->description('Filtra por owner (ex: wagner). Omite pra todos.'),
            'auto_comment' => $schema->boolean()
                ->description('Se true, posta comentário automático "🤖 Health check..." em cada task flagged via TaskCrudService. Default false (só relatório).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $owner = $request->get('owner') ? strtolower((string) $request->get('owner')) : null;
        $autoComment = (bool) $request->get('auto_comment', false);

        $command = app(McpTasksHealthCheckCommand::class);
        $flagged = $command->scanStaleness($owner);

        if ($autoComment && $flagged->isNotEmpty()) {
            // Roda via Artisan pra reaproveitar postAutoComments (protected).
            // Output buffer descartado — só interessa side-effect (comentários criados).
            Artisan::call('mcp:tasks:health-check', array_filter([
                '--auto-comment' => true,
                '--owner' => $owner,
            ], fn ($v) => $v !== null));
        }

        $md = $command->renderMarkdown($flagged, $owner, $autoComment);

        return Response::text($md);
    }
}
