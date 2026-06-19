<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

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

    protected string $description = 'Busca full-text nas 90+ ADRs (decisões arquiteturais) do oimpresso. Retorna top 5 matches com slug, título e trecho relevante. Por padrão retorna ADRs ativas (aceito/accepted/accepted-historical) + recusadas (o NÃO consultável — responde "por que não temos X?", anti-relitígio) — use include_archived=true pra ver superseded/deprecated. Triagem 2026-05-06 ([_INDEX-LIFECYCLE](memory/decisions/_INDEX-LIFECYCLE.md)).';

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
            'include_archived' => $schema->boolean()
                ->default(false)
                ->description('Se true, inclui ADRs superseded/deprecated/sunsetting na busca. Default false (ativas + recusadas: accepted/accepted-historical/recusado — o NÃO consultável).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query', '');
        $limit = (int) $request->get('limit', 5);
        $limit = max(1, min(20, $limit));
        $includeArchived = (bool) $request->get('include_archived', false);

        if (trim($query) === '') {
            return Response::error('Parâmetro "query" obrigatório.');
        }

        $user = $request->user();
        $businessId = (int) data_get($user, 'business_id', 0);

        // Gap #2 (US-RET-001) — pipeline bom HYBRID atrás de flag, fallback FULLTEXT.
        // Corpus MCP é GLOBAL (sem filtro business_id — verificado no índice CT 100).
        // Só no caminho ativo (índice exclui superseded; archived continua FULLTEXT).
        $rows = null;
        if (! $includeArchived && config('copiloto.mcp_search.docs_pipeline', false)) {
            try {
                $hybrid = McpMemoryDocument::buscarHybrid($query, $limit, $user instanceof \App\User ? $user : null, 'adr', null, $businessId);
                if ($hybrid->isNotEmpty()) {
                    $rows = $hybrid;
                }
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning('decisions-search: hybrid falhou, fallback FULLTEXT: '.$e->getMessage());
            }
        }

        // FULLTEXT (default / fallback). Filtra permissão Spatie + status lifecycle.
        if ($rows === null) {
            $q = McpMemoryDocument::doTipo('adr')
                ->acessiveisPara($user)
                ->porStatusAtivo($includeArchived)
                ->buscarTexto($query);

            if ($businessId > 0) {
                $q->doBusiness($businessId);
            }

            $rows = $q->limit($limit)->get(['slug', 'title', 'content_md', 'metadata']);
        }

        if ($rows->isEmpty()) {
            $hint = $includeArchived
                ? ''
                : "\n\n💡 Dica: tente `include_archived: true` se procura ADR superseded/deprecated.";
            return Response::text("Nenhum ADR encontrado pra query: \"$query\".$hint");
        }

        $scopeNote = $includeArchived
            ? '(incluindo superseded/deprecated)'
            : '(ativas + recusadas — aceito/accepted/accepted-historical/recusado)';

        $output = "Encontrados " . $rows->count() . " ADR(s) pra \"$query\" $scopeNote:\n\n";
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
