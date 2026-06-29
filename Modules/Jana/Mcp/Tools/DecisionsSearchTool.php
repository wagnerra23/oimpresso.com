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
 * Full-text search nas ADRs via MySQL FULLTEXT index na tabela
 * mcp_memory_documents. Retorna top matches com slug + título + resumo.
 *
 * O resumo prioriza (1) `summary` curado no frontmatter, (2) 1º parágrafo da
 * seção "## Decisão"/"## Contexto", (3) snippet posicional legado. Origem:
 * medição empírica 2026-06-29 — 53% dos snippets caíam em chunk posicional
 * cego (1ª palavra da query ancorando no meio do doc), sem resumir a decisão.
 */
class DecisionsSearchTool extends Tool
{
    protected string $name = 'decisions-search';

    protected string $title = 'Buscar ADRs do projeto';

    protected string $description = 'Busca full-text nas ADRs (decisões arquiteturais) do oimpresso. Retorna top matches com slug, título e resumo da decisão. Por padrão retorna ADRs ativas (aceito/accepted/accepted-historical) + recusadas (o NÃO consultável — responde "por que não temos X?", anti-relitígio) — use include_archived=true pra ver superseded/deprecated. Triagem 2026-05-06 ([_INDEX-LIFECYCLE](memory/decisions/_INDEX-LIFECYCLE.md)).';

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
            $snippet = $this->montarResumo($doc, $query, 240);
            $output .= "## " . $doc->slug . "\n";
            $output .= "**" . $doc->title . "**\n";
            $output .= $snippet . "\n";
            $output .= "_Use `decisions-fetch slug={$doc->slug}` pra ler completo._\n\n";
        }

        return Response::text($output);
    }

    /**
     * Monta o resumo exibido pra cada ADR no resultado da busca.
     *
     * Ordem de qualidade, com degradação graciosa:
     *   1. `summary` curado no frontmatter (metadata) — melhor, preenchível incrementalmente;
     *   2. 1º parágrafo da seção "## Decisão"/"## Contexto" — determinístico, resume a decisão;
     *   3. extrairSnippet posicional (legado) — fallback que nunca regride.
     *
     * Medição 2026-06-29: 53% dos snippets caíam no caso (3) com chunk posicional cego.
     */
    protected function montarResumo(McpMemoryDocument $doc, string $query, int $maxLen = 240): string
    {
        $summary = data_get($doc->metadata, 'summary');
        if (is_string($summary) && trim($summary) !== '') {
            return $this->normalizarResumo($summary, $maxLen);
        }

        $decisao = $this->extrairResumoDecisao((string) $doc->content_md);
        if ($decisao !== null) {
            return $this->normalizarResumo($decisao, $maxLen);
        }

        return $this->extrairSnippet((string) $doc->content_md, $query, $maxLen);
    }

    /**
     * Extrai o 1º parágrafo significativo da primeira seção canônica de uma ADR
     * (Decisão > Contexto > Resumo > TL;DR). Retorna null se nenhuma seção bate.
     */
    protected function extrairResumoDecisao(string $body): ?string
    {
        foreach (['Decis[ãa]o', 'Contexto', 'Resumo', 'TL;?DR'] as $secao) {
            if (! preg_match('/^#{1,4}[ \t]*' . $secao . '[ \t]*$/imu', $body, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // PREG_OFFSET_CAPTURE devolve offsets em BYTES mesmo com /u — substr (bytes) é correto.
            $inicio = $m[0][1] + strlen($m[0][0]);
            $resto = substr($body, $inicio);

            // Corta no próximo header markdown (fim da seção).
            if (preg_match('/^#{1,4}[ \t]/mu', $resto, $hm, PREG_OFFSET_CAPTURE)) {
                $resto = substr($resto, 0, $hm[0][1]);
            }

            $paragrafo = $this->primeiroParagrafo($resto);
            if ($paragrafo !== null) {
                return $paragrafo;
            }
        }

        return null;
    }

    /**
     * 1º bloco de texto não-vazio de um trecho, pulando linhas em branco,
     * blockquotes (>), separadores (---) e linhas de metadata (Status:/Data:/etc).
     */
    protected function primeiroParagrafo(string $trecho): ?string
    {
        $buffer = [];

        foreach (preg_split('/\r?\n/', $trecho) as $linha) {
            $t = trim($linha);

            if ($buffer === []) {
                if ($t === '' || $t === '---' || str_starts_with($t, '>')
                    || preg_match('/^\*{0,2}(Status|Data|Decisores|Tags|Supersedes)\*{0,2}\s*:/i', $t)) {
                    continue;
                }
                $buffer[] = $t;
                continue;
            }

            if ($t === '') {
                break;
            }
            $buffer[] = $t;
        }

        return $buffer === [] ? null : implode(' ', $buffer);
    }

    /**
     * Normaliza um resumo pra exibição: remove markdown inline ruidoso,
     * colapsa espaços e trunca a maxLen sem cortar caractere multibyte.
     */
    protected function normalizarResumo(string $texto, int $maxLen): string
    {
        $texto = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $texto);
        $texto = str_replace(['**', '`'], '', (string) $texto);
        $texto = trim((string) preg_replace('/\s+/', ' ', (string) $texto));

        if (mb_strlen($texto) > $maxLen) {
            $texto = rtrim(mb_substr($texto, 0, $maxLen)) . '…';
        }

        return $texto;
    }

    /**
     * Extrai trecho relevante ao redor da primeira ocorrência da query (fallback legado).
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
