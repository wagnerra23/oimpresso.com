<?php

declare(strict_types=1);

use Modules\Jana\Entities\Mcp\McpMemoryDocument;

uses(Tests\TestCase::class);

/**
 * US-RET-001 (SPEC-retrieval-tools-mcp-unificado, gap #2) — pré-req multi-tenant.
 *
 * Pra rotear as tools MCP (decisions-search/memoria-search/kb-answer) pelo pipeline
 * hybrid SEM vazar tenant, o índice Meilisearch precisa de `business_id` filterável.
 * Aqui blindamos que toSearchableArray emite `business_id` — incl. NULL (doc plataforma
 * = ADR visível a todos). O routing em si fica pra US-RET-003 + reindex CT 100.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): sem business_id no índice, o filtro
 * de tenant some no search → vazamento cross-business (pior bug do projeto).
 */

it('toSearchableArray inclui business_id (pré-req tenant-safe do routing)', function () {
    $doc = new McpMemoryDocument();
    $doc->business_id = 4;
    $doc->content_md = '# doc de teste';

    $arr = $doc->toSearchableArray();

    expect($arr)->toHaveKey('business_id')
        ->and($arr['business_id'])->toBe(4);
});

it('business_id NULL (doc plataforma/ADR) é preservado no índice, não some', function () {
    $doc = new McpMemoryDocument();
    $doc->business_id = null;
    $doc->content_md = '# ADR plataforma';

    expect($doc->toSearchableArray())->toHaveKey('business_id')
        ->and($doc->toSearchableArray()['business_id'])->toBeNull();
});
