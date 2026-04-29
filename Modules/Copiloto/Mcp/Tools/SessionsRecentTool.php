<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Tool sessions-recent.
 *
 * Lista os últimos N session logs cronológicos. Útil pra agentes
 * entenderem contexto recente do projeto sem precisar carregar todos.
 */
class SessionsRecentTool extends Tool
{
    protected string $name = 'sessions-recent';

    protected string $title = 'Últimos session logs';

    protected string $description = 'Lista os N session logs mais recentes do projeto (chronological). Útil pra entender o que foi feito recentemente. Cada linha tem slug + título + data.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->default(5)
                ->description('Quantos sessions retornar (default 5, max 20)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $limit = (int) $request->get('limit', 5);
        $limit = max(1, min(20, $limit));

        $user = $request->user();

        $rows = McpMemoryDocument::doTipo('session')
            ->acessiveisPara($user)
            ->orderByDesc('indexed_at')
            ->limit($limit)
            ->get(['slug', 'title', 'indexed_at', 'metadata']);

        if ($rows->isEmpty()) {
            return Response::text(
                "Nenhum session log indexado ainda. Rode `php artisan mcp:sync-memory` no app principal."
            );
        }

        $output = "Últimos " . $rows->count() . " session log(s):\n\n";
        foreach ($rows as $doc) {
            $output .= sprintf(
                "- **%s**\n  slug: `%s`\n  indexed: %s\n  Use `decisions-fetch` (ou variante de session) pra ler.\n\n",
                $doc->title,
                $doc->slug,
                $doc->indexed_at?->toDateString() ?? 'desconhecido'
            );
        }

        return Response::text($output);
    }
}
