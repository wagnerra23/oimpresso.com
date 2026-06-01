<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\KB\Http\Controllers\KbGraphController;

/**
 * Feature specs — KbGraphController@data (backend do grafo, ONDA 6, ADR 0150).
 *
 * Endpoint:
 *   GET /kb/graph/data → {nodes, edges, kpis} (formato ReactFlow/Cytoscape).
 *
 * Cobertura:
 *   - smoke: contrato {nodes, edges, kpis} + shapes internos
 *   - kpis: contagem + por_tipo agregado
 *   - cross-tenant Tier 0 (ADR 0093): user biz=1 NÃO vê nós/arestas de biz=99
 *   - auth: rota exige autenticação
 *
 * NOTA (handoff): a rota /kb/graph/data ainda é um closure placeholder no
 * momento desta escrita (Wagner pluga `KbGraphController@data`). Por isso os
 * asserts de CONTEÚDO instanciam o controller direto (`->data($req)`), o que
 * exercita o código real + o global scope (que lê session()) sem depender do
 * wiring da rota. O smoke HTTP valida só a estrutura que tanto o placeholder
 * quanto o controller satisfazem, e a verificação de auth/registro de rota.
 *
 * Tier 0: tests biz=1 OR biz=99 — NUNCA biz=4 (ROTA LIVRE prod). ADR 0101.
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

/** Insere um node direto (bypass observer) e devolve o id. */
function kbGraphSeedNode(int $bizId, string $slug, string $type = 'article', string $title = 'Nó'): int
{
    return \DB::table('kb_nodes')->insertGetId([
        'business_id' => $bizId,
        'type'        => $type,
        'slug'        => $slug,
        'title'       => $title,
        'is_editable' => $type === 'article',
        'body_blocks' => $type === 'article' ? json_encode([['kind' => 'para', 'text' => 'x']]) : null,
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────
// Smoke + contrato
// ─────────────────────────────────────────────────────────────────────────

it('registra a rota kb.graph.data', function () {
    expect(\Illuminate\Support\Facades\Route::has('kb.graph.data'))->toBeTrue();
});

it('GET /kb/graph/data responde {nodes, edges, kpis}', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->getJson('/kb/graph/data')
        ->assertOk()
        ->assertJsonStructure(['nodes', 'edges', 'kpis']);
});

it('data() devolve nodes no shape ReactFlow {id, label, type, category_id, group}', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    kbGraphSeedNode(1, 'sop-impressao', 'article', 'Como imprimir banner');

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    expect($payload['nodes'])->toHaveCount(1);
    $node = $payload['nodes'][0];
    expect($node)->toHaveKeys(['id', 'label', 'type', 'category_id', 'group'])
        ->and($node['label'])->toBe('Como imprimir banner')   // label = title
        ->and($node['type'])->toBe('article')
        ->and($node['group'])->toBe('article');                // group = type
});

it('data() devolve edges no shape {id, from, to, type, weight}', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $a = kbGraphSeedNode(1, 'no-a', 'article', 'A');
    $b = kbGraphSeedNode(1, 'no-b', 'article', 'B');

    \DB::table('kb_edges')->insert([
        'business_id'  => 1,
        'from_node_id' => $a,
        'to_node_id'   => $b,
        'edge_type'    => 'cross-link',
        'weight'       => 0.750,
        'generated_by' => 'manual',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    expect($payload['edges'])->toHaveCount(1);
    $edge = $payload['edges'][0];
    expect($edge)->toHaveKeys(['id', 'from', 'to', 'type', 'weight'])
        ->and($edge['from'])->toBe($a)            // from = from_node_id
        ->and($edge['to'])->toBe($b)              // to = to_node_id
        ->and($edge['type'])->toBe('cross-link')  // type = edge_type
        ->and($edge['weight'])->toBe(0.75);       // float, não string decimal
});

it('kpis traz contagem total + por_tipo agregado', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    kbGraphSeedNode(1, 'art-1', 'article', 'Art 1');
    kbGraphSeedNode(1, 'art-2', 'article', 'Art 2');
    kbGraphSeedNode(1, 'adr-1', 'adr', 'ADR 1');

    $a = \DB::table('kb_nodes')->where('slug', 'art-1')->value('id');
    $b = \DB::table('kb_nodes')->where('slug', 'art-2')->value('id');
    \DB::table('kb_edges')->insert([
        'business_id' => 1, 'from_node_id' => $a, 'to_node_id' => $b,
        'edge_type' => 'related-by-tag', 'weight' => 1.0, 'generated_by' => 'tag_overlap',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    expect($payload['kpis']['nodes'])->toBe(3)
        ->and($payload['kpis']['edges'])->toBe(1)
        ->and($payload['kpis']['por_tipo'])->toMatchArray(['article' => 2, 'adr' => 1]);
});

// ─────────────────────────────────────────────────────────────────────────
// Cross-tenant Tier 0 (ADR 0093) — biz=1 NUNCA enxerga biz=99
// ─────────────────────────────────────────────────────────────────────────

it('cross-tenant: user biz=1 nao ve nodes/edges de biz=99', function () {
    // biz=99: 2 nós + 1 aresta entre eles.
    $x = kbGraphSeedNode(99, 'secret-x', 'article', 'Secreto X');
    $y = kbGraphSeedNode(99, 'secret-y', 'article', 'Secreto Y');
    \DB::table('kb_edges')->insert([
        'business_id' => 99, 'from_node_id' => $x, 'to_node_id' => $y,
        'edge_type' => 'cross-link', 'weight' => 1.0, 'generated_by' => 'manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // biz=1: 1 nó isolado.
    kbGraphSeedNode(1, 'meu-no', 'article', 'Meu nó biz 1');

    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    // Só o nó de biz=1, zero arestas de biz=99.
    expect($payload['nodes'])->toHaveCount(1)
        ->and($payload['nodes'][0]['label'])->toBe('Meu nó biz 1')
        ->and($payload['edges'])->toHaveCount(0)
        ->and($payload['kpis']['nodes'])->toBe(1)
        ->and($payload['kpis']['edges'])->toBe(0);

    // E o conteúdo de biz=99 NÃO aparece em lugar nenhum do payload.
    expect(json_encode($payload))->not->toContain('Secreto X')
        ->and(json_encode($payload))->not->toContain('Secreto Y');
});

it('cross-tenant: user biz=99 ve apenas seus proprios nodes', function () {
    kbGraphSeedNode(1, 'biz1-node', 'article', 'Biz 1 node');
    kbGraphSeedNode(99, 'biz99-node', 'article', 'Biz 99 node');

    kbActAsUser(bizId: 99, permissions: ['copiloto.mcp.memory.manage']);

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    expect($payload['nodes'])->toHaveCount(1)
        ->and($payload['nodes'][0]['label'])->toBe('Biz 99 node')
        ->and($payload['kpis']['nodes'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────
// Auth
// ─────────────────────────────────────────────────────────────────────────

it('exige autenticação', function () {
    $this->getJson('/kb/graph/data')->assertStatus(401);
});
