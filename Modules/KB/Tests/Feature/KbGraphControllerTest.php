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
 *   - kpis: total_nodes/total_edges + by_type agregado
 *   - cross-tenant Tier 0 (ADR 0093): user biz=1 NÃO vê nós/arestas de biz=99
 *   - auth: rota exige autenticação
 *
 * NOTA: os asserts de CONTEÚDO instanciam o controller direto (`->data($req)`),
 * exercitando o buildGraph() real + o global scope (que lê session()) sem passar
 * por middleware. O smoke HTTP + auth + registro-de-rota cobrem o wiring. Hoje
 * /kb/graph/data → KbGraphController@data e /kb/graph → @page; ambos reusam o
 * mesmo buildGraph(), então o shape testado aqui vale pros dois consumidores.
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

it('data() devolve nodes no shape KbGraphNode {id, type, data{label,slug}}', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $id = kbGraphSeedNode(1, 'sop-impressao', 'article', 'Como imprimir banner');

    $payload = (new KbGraphController())->data(Request::create('/kb/graph/data'))->getData(true);

    expect($payload['nodes'])->toHaveCount(1);
    $node = $payload['nodes'][0];
    expect($node)->toHaveKeys(['id', 'type', 'data'])
        ->and($node['id'])->toBe('article-' . $id)                  // id = "<type>-<id>"
        ->and($node['type'])->toBe('article')
        ->and($node['data']['label'])->toBe('Como imprimir banner') // label dentro de data{}
        ->and($node['data']['slug'])->toBe('sop-impressao');
});

it('data() devolve edges no shape KbGraphEdge {id, source, target, edge_type, weight}', function () {
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
    expect($edge)->toHaveKeys(['id', 'source', 'target', 'edge_type', 'weight'])
        ->and($edge['source'])->toBe('article-' . $a)  // source = "<type>-<from_node_id>"
        ->and($edge['target'])->toBe('article-' . $b)  // target = "<type>-<to_node_id>"
        ->and($edge['edge_type'])->toBe('cross-link')
        ->and($edge['weight'])->toBe(0.75);            // float, não string decimal
});

it('kpis traz total_nodes/total_edges + by_type agregado', function () {
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

    expect($payload['kpis']['total_nodes'])->toBe(3)
        ->and($payload['kpis']['total_edges'])->toBe(1)
        ->and($payload['kpis']['by_type'])->toMatchArray(['article' => 2, 'adr' => 1]);
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
        ->and($payload['nodes'][0]['data']['label'])->toBe('Meu nó biz 1')
        ->and($payload['edges'])->toHaveCount(0)
        ->and($payload['kpis']['total_nodes'])->toBe(1)
        ->and($payload['kpis']['total_edges'])->toBe(0);

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
        ->and($payload['nodes'][0]['data']['label'])->toBe('Biz 99 node')
        ->and($payload['kpis']['total_nodes'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────
// Auth
// ─────────────────────────────────────────────────────────────────────────

it('exige autenticação', function () {
    $this->getJson('/kb/graph/data')->assertStatus(401);
});
