<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Resource memory/current.
 *
 * Expõe CURRENT.md (cycle/sprint vivo). Equivalente à tool tasks-current
 * mas como resource cacheável URI — alguns clients MCP preferem Resources.
 */
#[Uri('oimpresso://memory/current')]
#[MimeType('text/markdown')]
class CurrentResource extends Resource
{
    protected string $name = 'current';

    protected string $title = 'Cycle/Sprint atual';

    protected string $description = 'CURRENT.md vivo — cycle ativo, tarefas WIP, on-deck, bloqueios, métricas.';

    public function handle(): Response
    {
        $doc = McpMemoryDocument::where('slug', 'current')
            ->orWhere('type', 'current')
            ->first();

        if ($doc === null) {
            return Response::text(
                "# CURRENT.md ainda não sincronizado\n\nRode `php artisan mcp:sync-memory` no app principal."
            );
        }

        return Response::text($doc->content_md);
    }
}
