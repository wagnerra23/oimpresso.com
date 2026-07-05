<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

uses(Tests\TestCase::class);

/**
 * Gap #2 US-RET-001/003 — decisions-search/kb-answer via HYBRID no corpus MCP (global).
 *
 * buscarHybrid chama a API REST do Meilisearch DIRETAMENTE (US-RET-003, 2026-07-04):
 * o SCOUT_DRIVER do ambiente resolve pro default 'collection', cujo engine ignora o
 * parâmetro `hybrid` — então o antigo Scout::search() nunca exercia o retrieval
 * semântico (recall@5 lexical = 0.074 no golden set; via hybrid = 0.704, ~9.5x —
 * medido live no CT 100). Estes smokes garantem que o método degrada limpo quando o
 * Meilisearch está inacessível (→ Collection vazia, dispara o fallback FULLTEXT no
 * caller) e continua callable com tipo/module opcionais, sem tocar a rede real.
 */

it('buscarHybrid degrada limpo quando o Meilisearch falha (→ Collection vazia, sem fatal)', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $r = McpMemoryDocument::buscarHybrid('isolamento multi-tenant', 5, null, 'adr');

    expect($r)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class)
        ->and($r)->toBeEmpty();
});

it('buscarHybrid retorna Collection vazia quando não há hits, com tipo/module opcionais', function () {
    Http::fake(['*' => Http::response(['hits' => []], 200)]);

    expect(McpMemoryDocument::buscarHybrid('x', 3, null))->toBeEmpty()
        ->and(McpMemoryDocument::buscarHybrid('x', 3, null, 'spec', 'Financeiro'))->toBeEmpty();
});

/**
 * ADR 0322 — instruction-prefix qwen3: a query prefixada é embeddada no Ollama e vai
 * como `vector`; o `q` segue RAW (lado lexical não pode ver o prefixo). Ollama fora →
 * degrada pro hybrid raw (sem `vector`), nunca quebra a busca.
 */
function fakeConfigPrefix(): void
{
    config()->set('scout.meilisearch.host', 'http://meili.test');
    config()->set('copiloto.mcp_search.docs_query_instruction', "Instruct: acha o doc.\nQuery: ");
    config()->set('copiloto.meilisearch_indexes.mcp_memory_documents.embedders.qwen3_local', [
        'url'   => 'http://ollama.test/api/embeddings',
        'model' => 'qwen3-embedding:0.6b',
    ]);
}

it('buscarHybrid manda vector do embedding prefixado e mantém q raw (ADR 0322)', function () {
    fakeConfigPrefix();
    Http::fake([
        'http://ollama.test/*' => Http::response(['embedding' => [0.1, 0.2, 0.3]], 200),
        'http://meili.test/*'  => Http::response(['hits' => []], 200),
    ]);

    McpMemoryDocument::buscarHybrid('daily brief', 5, null);

    Http::assertSent(function ($req) {
        return str_starts_with($req->url(), 'http://ollama.test')
            && $req['prompt'] === "Instruct: acha o doc.\nQuery: daily brief";
    });
    Http::assertSent(function ($req) {
        return str_starts_with($req->url(), 'http://meili.test')
            && $req['q'] === 'daily brief' // raw — sem prefixo no lado lexical
            && $req['vector'] === [0.1, 0.2, 0.3];
    });
});

it('buscarHybrid degrada pro hybrid raw (sem vector) quando o Ollama falha', function () {
    fakeConfigPrefix();
    Http::fake([
        'http://ollama.test/*' => Http::response('', 500),
        'http://meili.test/*'  => Http::response(['hits' => []], 200),
    ]);

    expect(McpMemoryDocument::buscarHybrid('daily brief', 5, null))->toBeEmpty();

    Http::assertSent(function ($req) {
        return str_starts_with($req->url(), 'http://meili.test')
            && $req['q'] === 'daily brief'
            && ! array_key_exists('vector', $req->data());
    });
});

it('buscarHybrid não chama o Ollama quando a instrução está vazia (prefix desligado)', function () {
    fakeConfigPrefix();
    config()->set('copiloto.mcp_search.docs_query_instruction', '');
    Http::fake(['http://meili.test/*' => Http::response(['hits' => []], 200)]);

    expect(McpMemoryDocument::buscarHybrid('daily brief', 5, null))->toBeEmpty();

    Http::assertNotSent(fn ($req) => str_starts_with($req->url(), 'http://ollama.test'));
});
