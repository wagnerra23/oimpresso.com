<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Resource memory/handoff.
 *
 * Expõe `memory/08-handoff.md` como recurso MCP cacheável.
 * Cliente pode usar Resources/read pra carregar quando precisar de
 * contexto canônico mais recente do projeto.
 */
#[Uri('oimpresso://memory/handoff')]
#[MimeType('text/markdown')]
class HandoffResource extends Resource
{
    protected string $name = 'handoff';

    protected string $title = 'Handoff canônico oimpresso';

    protected string $description = 'Estado canônico mais recente do projeto (memory/08-handoff.md). Substitui leitura repetida — agente carrega aqui sob demanda.';

    public function handle(): Response
    {
        $doc = McpMemoryDocument::where('slug', 'handoff')
            ->orWhere('type', 'handoff')
            ->first();

        if ($doc === null) {
            return Response::text(
                "# Handoff ainda não sincronizado\n\nRode `php artisan mcp:sync-memory` no app principal."
            );
        }

        return Response::text($doc->content_md);
    }
}
