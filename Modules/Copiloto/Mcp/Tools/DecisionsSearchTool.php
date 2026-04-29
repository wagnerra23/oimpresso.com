<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Tool decisions-search.
 *
 * Full-text search nos 53 ADRs via MySQL FULLTEXT index na tabela
 * mcp_memory_documents. Retorna top 5 matches com slug + título + snippet.
 */
class DecisionsSearchTool extends Tool
{
    protected string $name = 'decisions-search';

    protected string $title = 'Buscar ADRs do projeto';

    protected string $description = 'Busca full-text nos 53 ADRs (decisões arquiteturais) do oimpresso. Retorna top 5 matches com slug, título e trecho relevante. Use slug retornado em decisions-fetch pra ler ADR completa.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->required()
                ->description('Termos de busca (ex: "Vizra ADK", "MCP server", "ContextoNegocio", "memória recall")'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(20)
                ->default(5)
                ->description('Quantos resultados retornar (default 5, max 20)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query', '');
        $limit = (int) $request->get('limit', 5);
        $limit = max(1, min(20, $limit));

        if (trim($query) === '') {
            return Response::error('Parâmetro "query" obrigatório.');
        }

        $user = $request->user();

        // Filtra por permissão Spatie (admin_only respeita user_role)
        $rows = McpMemoryDocument::doTipo('adr')
            ->acessiveisPara($user)
            ->buscarTexto($query)
            ->limit($limit)
            ->get(['slug', 'title', 'content_md', 'metadata']);

        if ($rows->isEmpty()) {
            return Response::text("Nenhum ADR encontrado pra query: \"$query\".");
        }

        $output = "Encontrados " . $rows->count() . " ADR(s) pra \"$query\":\n\n";
        foreach ($rows as $doc) {
            $snippet = $this->extrairSnippet($doc->content_md, $query, 200);
            $output .= "## " . $doc->slug . "\n";
            $output .= "**" . $doc->title . "**\n";
            $output .= $snippet . "\n";
            $output .= "_Use `decisions-fetch slug={$doc->slug}` pra ler completo._\n\n";
        }

        return Response::text($output);
    }

    /**
     * Extrai trecho relevante ao redor da primeira ocorrência da query.
     */
    protected function extrairSnippet(string $body, string $query, int $maxLen = 200): string
    {
        $words = preg_split('/\s+/', trim($query));
        $firstWord = $words[0] ?? '';

        if ($firstWord === '') {
            return mb_substr($body, 0, $maxLen) . '...';
        }

        $pos = mb_stripos($body, $firstWord);
        if ($pos === false) {
            return mb_substr($body, 0, $maxLen) . '...';
        }

        $start = max(0, $pos - 80);
        $snippet = mb_substr($body, $start, $maxLen);

        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        if ($start + $maxLen < mb_strlen($body)) {
            $snippet .= '...';
        }

        return $snippet;
    }
}
