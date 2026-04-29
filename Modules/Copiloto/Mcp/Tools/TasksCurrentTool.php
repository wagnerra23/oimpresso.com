<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Tool tasks-current.
 *
 * Retorna o conteúdo do CURRENT.md (cycle/sprint vivo). Query SQL na
 * tabela mcp_memory_documents (cache governado). Audit log gravado
 * automaticamente pelo middleware McpAuth.
 */
class TasksCurrentTool extends Tool
{
    protected string $name = 'tasks-current';

    protected string $title = 'Tarefas atuais (CURRENT.md)';

    protected string $description = 'Retorna o estado vivo do cycle/sprint atual: tarefas ativas, on-deck, bloqueios, métricas. Lê CURRENT.md sincronizado do git.';

    public function handle(): Response
    {
        $current = McpMemoryDocument::where('slug', 'current')
            ->orWhere('type', 'current')
            ->first();

        if ($current === null) {
            return Response::text(
                "CURRENT.md ainda não foi sincronizado. Rode `php artisan mcp:sync-memory --reason=manual` no app principal."
            );
        }

        return Response::text(sprintf(
            "# %s\n\n%s\n\n---\n_Indexed: %s · git_sha: %s_",
            $current->title,
            $current->content_md,
            $current->indexed_at?->toIso8601String() ?? 'desconhecido',
            $current->git_sha ?? 'desconhecido'
        ));
    }
}
