<?php

declare(strict_types=1);

use Modules\Jana\Entities\Mcp\McpMemoryDocument;

uses(Tests\TestCase::class);

/**
 * Gap #2 US-RET-001 — decisions-search/kb-answer via HYBRID no corpus MCP (global).
 *
 * buscarHybrid foi VERIFICADO live no índice CT 100 (embedder qwen3_local, filtro
 * status/type, retornou ADRs corretos). Aqui o smoke garante que o método é callable
 * e degrada limpo com SCOUT_DRIVER=null (CI) — devolve Collection vazia, nunca fatal,
 * o que dispara o fallback FULLTEXT nas tools. Recall real é validado em prod (US-RET-003).
 */

it('buscarHybrid é callable e degrada limpo (SCOUT_DRIVER=null → Collection vazia, sem fatal)', function () {
    $r = McpMemoryDocument::buscarHybrid('isolamento multi-tenant', 5, null, 'adr');
    expect($r)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class)
        ->and($r)->toBeEmpty();
});

it('buscarHybrid aceita tipo/module opcionais sem quebrar', function () {
    expect(McpMemoryDocument::buscarHybrid('x', 3, null))->toBeEmpty()
        ->and(McpMemoryDocument::buscarHybrid('x', 3, null, 'spec', 'Financeiro'))->toBeEmpty();
});
