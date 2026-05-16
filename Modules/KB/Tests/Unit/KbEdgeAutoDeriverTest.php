<?php

declare(strict_types=1);

/**
 * Unit specs do KbEdgeAutoDeriver.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §4 (diretrizes de uso)
 *
 * Deriver gera edges automáticas baseado em:
 *   - cross-link: regex `#kb-XXX` em body_blocks de artigos editáveis
 *   - related-by-tag: tag overlap (3+ tags em comum → weight=0.6, fórmula
 *     |intersect|/|union| ou similar)
 *   - supersedes/charter-of: frontmatter de mcp_memory_documents (testado em
 *     KbBridgeFromMcpJobTest)
 *
 * TODO[CL]: Agent A define FQCN — provavelmente Modules\KB\Services\KbEdgeAutoDeriver.
 * Pode ser standalone service (signature ::derive(array $nodes): int) OU
 * 1 método por estratégia.
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('creates cross-link edges when body_blocks contains #kb-XXX references', function () {
    kbActAsUser(bizId: 1);

    // Node A com body contendo #kb-42 → cross-link pra node 42
    $nodeA = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'a',
        'title' => 'A', 'is_editable' => true,
        'body_blocks' => json_encode([
            ['kind' => 'para', 'text' => 'Veja #kb-42 pra mais info.'],
        ]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $nodeTarget = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'target',
        'title' => 'Target', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'target']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Re-aliasing pra que #kb-42 corresponda ao nodeTarget — Agent A define
    // se a regex usa id literal OR alias. Aqui usamos id real do nodeTarget.

    $deriverClass = guessKbEdgeDeriverClass();
    // Re-escrevemos o body pra usar id real
    \DB::table('kb_nodes')->where('id', $nodeA)->update([
        'body_blocks' => json_encode([
            ['kind' => 'para', 'text' => "Veja #kb-{$nodeTarget} pra mais info."],
        ]),
    ]);

    $deriver = new $deriverClass();
    $deriver->deriveForNode($nodeA);  // TODO[CL]: confirmar método

    $edge = \DB::table('kb_edges')
        ->where('from_node_id', $nodeA)
        ->where('to_node_id', $nodeTarget)
        ->where('edge_type', 'cross-link')
        ->first();

    expect($edge)->not->toBeNull();
});

it('creates related-by-tag edges when nodes share enough tags', function () {
    kbActAsUser(bizId: 1);

    // Node A tags: [roland, vs540, sangria, medida, qualidade]
    // Node B tags: [roland, vs540, sangria]
    // 3 tags em comum / 5 union = 0.6 weight
    $a = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'tag-a',
        'title' => 'A', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'tags' => json_encode(['roland', 'vs540', 'sangria', 'medida', 'qualidade']),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $b = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'tag-b',
        'title' => 'B', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'tags' => json_encode(['roland', 'vs540', 'sangria']),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $deriverClass = guessKbEdgeDeriverClass();
    $deriver = new $deriverClass();
    $deriver->deriveRelatedByTag(1);  // TODO[CL]: confirmar método

    $edge = \DB::table('kb_edges')
        ->where('business_id', 1)
        ->where('edge_type', 'related-by-tag')
        ->where(function ($q) use ($a, $b) {
            $q->where(function ($q) use ($a, $b) {
                $q->where('from_node_id', $a)->where('to_node_id', $b);
            })->orWhere(function ($q) use ($a, $b) {
                $q->where('from_node_id', $b)->where('to_node_id', $a);
            });
        })
        ->first();

    expect($edge)->not->toBeNull()
        ->and((float) $edge->weight)->toBe(0.6);
});

it('does NOT create related-by-tag for nodes with <2 tag overlap', function () {
    kbActAsUser(bizId: 1);
    $a = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'low-a',
        'title' => 'A', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'tags' => json_encode(['roland', 'vs540']),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $b = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'low-b',
        'title' => 'B', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'tags' => json_encode(['hp', 'latex']),  // ZERO overlap
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $deriverClass = guessKbEdgeDeriverClass();
    (new $deriverClass())->deriveRelatedByTag(1);

    expect(\DB::table('kb_edges')->where('edge_type', 'related-by-tag')->count())->toBe(0);
});

/**
 * Helper local — tenta classes possíveis ao FQCN.
 */
function guessKbEdgeDeriverClass(): string
{
    foreach (['Modules\\KB\\Services\\KbEdgeAutoDeriver',
              'Modules\\KB\\Services\\Bridge\\KbEdgeAutoDeriver',
              'Modules\\KB\\Services\\Graph\\KbEdgeAutoDeriver'] as $candidate) {
        if (class_exists($candidate)) {
            return $candidate;
        }
    }
    test()->markTestSkipped('KbEdgeAutoDeriver ainda não criado pelo Agent A. Esperado em Modules\\KB\\Services\\KbEdgeAutoDeriver.');
}
