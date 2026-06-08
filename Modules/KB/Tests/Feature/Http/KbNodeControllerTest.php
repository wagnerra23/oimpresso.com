<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

/**
 * Feature specs — endpoints REST KbNode (SCHEMA-DB-V1 §11).
 *
 * Endpoints cobertos:
 *   GET    /kb                            Inertia 'kb/Index'
 *   GET    /kb/nodes                      JSON paginado (cursor pagination)
 *   GET    /kb/nodes/{slug}               JSON detalhe (+JOIN mcp se bridge)
 *   POST   /kb/nodes                      cria artigo (perm: kb.write)
 *   PUT    /kb/nodes/{slug}               edita (snapshot → kb_node_versions)
 *   DELETE /kb/nodes/{slug}               soft-delete (perm: kb.softdelete)
 *   POST   /kb/nodes/{slug}/restore       restore (perm: kb.restore)
 *   POST   /kb/nodes/{slug}/reverify      atualiza last_verified_at + zera outdated_votes
 *
 * Permissions assumem migração futura pra: kb.view, kb.write, kb.softdelete,
 * kb.restore, kb.publish.path, kb.publish.troubleshoot, kb.favorite, kb.comment,
 * kb.ai.ask, kb.graph.view (SCHEMA §12). TODO[CL]: Agent A registra novas.
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('GET /kb returns Inertia kb/Index component (perm: kb.view)', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $response = $this->get('/kb');

    $response->assertOk();
    // TODO[CL]: assertInertia component depende de KbController estar renderizando kb/Index.
    // Atualmente KbController@index renderiza 'kb/Index' (resources/js/Pages/kb/Index.tsx).
    // Pode estar via Inertia direto OR via vista Blade. Se Inertia:
    $response->assertInertia(fn (AssertableInertia $p) =>
        $p->component('kb/Index')->has('nodes')
    );
})->skip('Pendente Agent A criar GET /kb retornando Inertia kb/Index com prop nodes');
// TODO[CL]: remover skip quando Agent A confirmar contrato.

it('GET /kb/nodes?type=adr filters by type', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $mcpId = kbCreateMcpDoc(1, 'adr', ['slug' => '0093', 'title' => 'ADR 0093']);
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => '0093-bridge',
        'title' => 'ADR 0093 bridge', 'is_editable' => false, 'body_blocks' => null,
        'source_doc_id' => $mcpId,
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'article', 'slug' => 'art-1',
        'title' => 'Artigo 1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->getJson('/kb/nodes?type=adr');

    $response->assertOk();
    $data = $response->json();
    // Aceita formato paginado (com 'data') OR plain array
    $items = $data['data'] ?? $data['nodes'] ?? $data;
    expect(collect($items)->pluck('type')->all())->toBe(['adr']);
});

it('GET /kb/nodes?q=multi-tenant fuzzy search by title', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    \DB::table('kb_nodes')->insert([
        ['business_id' => 1, 'type' => 'article', 'slug' => 'mt', 'title' => 'Multi-tenant Tier 0 explained',
         'is_editable' => true, 'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'type' => 'article', 'slug' => 'other', 'title' => 'Sobre FSM Pipeline',
         'is_editable' => true, 'body_blocks' => json_encode([['kind' => 'para', 'text' => 'y']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/kb/nodes?q=multi-tenant');

    $response->assertOk();
    $items = $response->json('data') ?? $response->json('nodes') ?? $response->json();
    expect(collect($items)->pluck('slug')->all())->toContain('mt')
        ->and(collect($items)->pluck('slug')->all())->not->toContain('other');
});

it('POST /kb/nodes without kb.write returns 403', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);  // SEM kb.write

    $response = $this->postJson('/kb/nodes', [
        'type'    => 'article',
        'slug'    => 'unauthorized',
        'title'   => 'try create',
        'body_blocks' => [['kind' => 'para', 'text' => 'x']],
    ]);

    $response->assertForbidden();
});

it('POST /kb/nodes with kb.write creates node and snapshots initial version', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    $response = $this->postJson('/kb/nodes', [
        'type'        => 'article',
        'slug'        => 'novo-artigo',
        'title'       => 'Como medir sangria',
        'body_blocks' => [
            ['kind' => 'h2', 'text' => 'Passo 1'],
            ['kind' => 'para', 'text' => 'Use régua certificada.'],
        ],
        'tags'        => ['sangria', 'medida'],
    ]);

    $response->assertCreated();
    expect(\DB::table('kb_nodes')->where('slug', 'novo-artigo')->count())->toBe(1)
        ->and(\DB::table('kb_node_versions')->where('node_id', \DB::table('kb_nodes')->where('slug', 'novo-artigo')->value('id'))->count())->toBe(1);
});

it('PUT /kb/nodes/{slug} edits node and creates new version snapshot', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'edit-me',
        'title' => 'V1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'v1']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->putJson('/kb/nodes/edit-me', [
        'title'       => 'V2',
        'body_blocks' => [['kind' => 'para', 'text' => 'v2 atualizado']],
    ]);

    $response->assertOk();
    expect(\DB::table('kb_nodes')->where('id', $nodeId)->value('title'))->toBe('V2')
        ->and(\DB::table('kb_node_versions')->where('node_id', $nodeId)->count())->toBeGreaterThanOrEqual(1);
});

it('DELETE /kb/nodes/{slug} soft-deletes (perm: kb.softdelete)', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.softdelete']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'del-me',
        'title' => 'X', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->deleteJson('/kb/nodes/del-me');

    $response->assertOk();
    $row = \DB::table('kb_nodes')->where('id', $nodeId)->first();
    expect($row->deleted_at)->not->toBeNull();
});

it('POST /kb/nodes/{slug}/restore restores soft-deleted (perm: kb.restore)', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.restore']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'restore-me',
        'title' => 'X', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'deleted', 'deleted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->postJson('/kb/nodes/restore-me/restore');

    $response->assertOk();
    $row = \DB::table('kb_nodes')->where('id', $nodeId)->first();
    expect($row->deleted_at)->toBeNull();
});

it('POST /kb/nodes/{slug}/reverify updates last_verified_at and zeroes outdated_votes', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 're-me',
        'title' => 'Outdated article', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok',
        'outdated_votes' => 5,
        'last_verified_at' => now()->subMonths(6),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->postJson('/kb/nodes/re-me/reverify');

    $response->assertOk();
    $fresh = \DB::table('kb_nodes')->where('id', $nodeId)->first();
    expect((int) $fresh->outdated_votes)->toBe(0)
        ->and($fresh->last_verified_at)->not->toBeNull();
});

it('GET /kb/nodes/{slug} returns detail with JOIN content for bridge nodes', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $docId = kbCreateMcpDoc(1, 'adr', [
        'slug' => '0093-detail',
        'title' => 'ADR 0093 Detail',
        'content_md' => '# ADR 0093\n\nbusiness_id global scope...',
    ]);
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => '0093-bridge-detail',
        'title' => 'ADR 0093 bridge', 'is_editable' => false, 'body_blocks' => null,
        'source_doc_id' => $docId,
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->getJson('/kb/nodes/0093-bridge-detail');

    $response->assertOk();
    $body = $response->json();
    // Espera que content_md venha do JOIN com mcp_memory_documents
    // TODO[CL]: confirmar shape (content_md root vs node.source_doc.content_md)
    expect(json_encode($body))->toContain('global scope');
});
