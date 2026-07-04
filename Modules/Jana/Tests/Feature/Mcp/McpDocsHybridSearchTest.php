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
