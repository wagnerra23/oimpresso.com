<?php

declare(strict_types=1);

use Modules\KB\Entities\KbEdge;
use Modules\KB\Entities\KbNode;

/**
 * Unit specs do Model KbEdge.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §4
 *
 * Invariantes testadas:
 *   - business_id global scope (Tier 0)
 *   - CHECK from_node_id <> to_node_id (anti self-loop)
 *   - UNIQUE (business_id, from_node_id, to_node_id, edge_type)
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

/** Helper local — cria 2 nodes editáveis no biz dado e retorna seus IDs. */
function kbEdgeTestPairNodes(int $bizId): array
{
    $a = \DB::table('kb_nodes')->insertGetId([
        'business_id' => $bizId, 'type' => 'article', 'slug' => "a-{$bizId}-".uniqid(),
        'title' => 'A', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $b = \DB::table('kb_nodes')->insertGetId([
        'business_id' => $bizId, 'type' => 'article', 'slug' => "b-{$bizId}-".uniqid(),
        'title' => 'B', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    return [$a, $b];
}

it('scopes by business_id (biz=1 NAO ve edges biz=99)', function () {
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
    [$a1, $b1] = kbEdgeTestPairNodes(1);
    [$a9, $b9] = kbEdgeTestPairNodes(99);

    \DB::table('kb_edges')->insert([
        'business_id' => 1, 'from_node_id' => $a1, 'to_node_id' => $b1,
        'edge_type' => 'cross-link', 'weight' => 1.000,
        'generated_by' => 'manual', 'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('kb_edges')->insert([
        'business_id' => 99, 'from_node_id' => $a9, 'to_node_id' => $b9,
        'edge_type' => 'cross-link', 'weight' => 1.000,
        'generated_by' => 'manual', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1);

    $edges = KbEdge::all();
    expect($edges)->toHaveCount(1)
        ->and($edges->first()->business_id)->toBe(1);
});

it('blocks self-edges (from_node_id == to_node_id)', function () {
    kbActAsUser(bizId: 1);
    [$a, $b] = kbEdgeTestPairNodes(1);

    expect(function () use ($a) {
        $edge = new KbEdge();
        $edge->business_id  = 1;
        $edge->from_node_id = $a;
        $edge->to_node_id   = $a;  // INVALIDO
        $edge->edge_type    = 'cross-link';
        $edge->save();
    })->toThrow(\Throwable::class);
    // TODO[CL]: a Exception pode vir do MySQL CHECK constraint OU de um
    // Observer em PHP. Em SQLite o CHECK tabela-level NÃO é replicado pelas
    // migrations (so via raw ALTER). Espera-se que Agent A adicione observer
    // PHP que valide from != to em saving event.
});

it('uniques the edge triple (business + from + to + edge_type)', function () {
    kbActAsUser(bizId: 1);
    [$a, $b] = kbEdgeTestPairNodes(1);

    $edge1 = new KbEdge();
    $edge1->business_id  = 1;
    $edge1->from_node_id = $a;
    $edge1->to_node_id   = $b;
    $edge1->edge_type    = 'cross-link';
    $edge1->save();

    expect(function () use ($a, $b) {
        $edge2 = new KbEdge();
        $edge2->business_id  = 1;
        $edge2->from_node_id = $a;
        $edge2->to_node_id   = $b;
        $edge2->edge_type    = 'cross-link';  // MESMO TRIPLE → UNIQUE VIOLATION
        $edge2->save();
    })->toThrow(\Throwable::class);
});

it('allows multiple edge_types between same pair', function () {
    kbActAsUser(bizId: 1);
    [$a, $b] = kbEdgeTestPairNodes(1);

    $e1 = new KbEdge();
    $e1->business_id = 1; $e1->from_node_id = $a; $e1->to_node_id = $b;
    $e1->edge_type = 'cross-link';
    $e1->save();

    $e2 = new KbEdge();
    $e2->business_id = 1; $e2->from_node_id = $a; $e2->to_node_id = $b;
    $e2->edge_type = 'related-by-tag'; $e2->weight = 0.6;
    $e2->save();

    expect(KbEdge::where('from_node_id', $a)->where('to_node_id', $b)->count())->toBe(2);
});

it('persists payload as JSON', function () {
    kbActAsUser(bizId: 1);
    [$a, $b] = kbEdgeTestPairNodes(1);

    $e = new KbEdge();
    $e->business_id = 1; $e->from_node_id = $a; $e->to_node_id = $b;
    $e->edge_type = 'cross-link';
    $e->payload   = ['block_idx' => 3];
    $e->save();

    $fresh = KbEdge::where('id', $e->id)->first();
    expect($fresh->payload)->toBeArray()
        ->and($fresh->payload)->toBe(['block_idx' => 3]);
});
