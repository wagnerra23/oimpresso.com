<?php

declare(strict_types=1);

use Modules\KB\Entities\KbNode;

/**
 * Cross-tenant isolation specs (Tier 0 IRREVOGÁVEL — ADR 0093).
 *
 * Cobre especificamente o RISCO R5 da ADR 0149:
 *   "Multi-tenant leak via bridge cross-business"
 *
 * Também ataca riscos relacionados:
 *   R1 duplicação acidental kb_nodes ↔ mcp_memory_documents (Tier 0 via bridge)
 *
 * Cenário: 2 businesses (1 e 99) — user de biz=1 NÃO PODE ver/modificar
 * NADA de biz=99, e vice-versa. Mesmo se autenticado como admin.
 *
 * Wagner palavras textuais (ADR 0093): "vazar dados entre tenants é o pior
 * bug possível neste projeto". biz=4 (ROTA LIVRE prod) NUNCA em tests.
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

it('blocks kb_node read across businesses (R5)', function () {
    // Cria node em biz=99
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'secret-biz99',
        'title' => 'Conteúdo confidencial biz 99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'SECRET']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    // Via Eloquent (global scope)
    expect(KbNode::all())->toHaveCount(0);

    // Via HTTP — GET detalhe SLUG conhecido NÃO deve retornar conteúdo
    $response = $this->getJson('/kb/nodes/secret-biz99');
    expect($response->status())->toBeIn([403, 404]);
});

it('blocks kb_edge creation across businesses (R5)', function () {
    // Cria 2 nodes em biz=99
    $a99 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 99, 'type' => 'article', 'slug' => 'a99',
        'title' => 'A99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $b99 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 99, 'type' => 'article', 'slug' => 'b99',
        'title' => 'B99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Cria node em biz=1
    $a1 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'a1',
        'title' => 'A1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // User de biz=1 admin tenta criar edge from=a1 (biz=1) → to=a99 (biz=99)
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    // Via HTTP — espera-se 403 ou 422 (validação de business_id)
    // TODO[CL]: confirmar endpoint com Agent A. Provavelmente POST /kb/edges.
    // Se endpoint não existe ainda, marcar como skip.
    if (!\Illuminate\Support\Facades\Route::has('kb.edges.store')) {
        test()->markTestSkipped('Endpoint POST /kb/edges não criado ainda — Agent A pendente.');
    }

    $response = $this->postJson('/kb/edges', [
        'from_node_id' => $a1,
        'to_node_id'   => $a99,  // cross-tenant
        'edge_type'    => 'cross-link',
    ]);

    expect($response->status())->toBeIn([403, 404, 422]);
    expect(\DB::table('kb_edges')->where('to_node_id', $a99)->count())->toBe(0);
});

it('blocks kb_comment cross-tenant (user biz=1 nao comenta node biz=99)', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'biz99-comm',
        'title' => 'biz 99 commentable', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.comment']);

    $response = $this->postJson('/kb/nodes/biz99-comm/comments', [
        'block_idx' => 0,
        'text'      => 'try cross-tenant',
    ]);

    expect($response->status())->toBeIn([403, 404]);
    expect(\DB::table('kb_comments')->count())->toBe(0);
});

it('blocks kb_favorite cross-tenant', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'biz99-fav',
        'title' => 'biz 99 to favorite', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.favorite']);

    $response = $this->postJson('/kb/nodes/biz99-fav/favorite');

    expect($response->status())->toBeIn([403, 404]);
    expect(\DB::table('kb_favorites')->count())->toBe(0);
});

it('bridge job respects business scope (job(1) nao toca docs biz=99)', function () {
    // Cria mcp_docs em ambos businesses
    kbCreateMcpDoc(1, 'adr', ['slug' => 'biz1-adr', 'title' => 'biz 1 adr']);
    kbCreateMcpDoc(99, 'adr', ['slug' => 'biz99-adr', 'title' => 'biz 99 adr']);

    // Roda job APENAS pra biz=1
    foreach (['Modules\\KB\\Jobs\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\Bridge\\KbBridgeFromMcpJob'] as $candidate) {
        if (class_exists($candidate)) {
            $jobClass = $candidate;
            break;
        }
    }
    if (!isset($jobClass)) {
        test()->markTestSkipped('KbBridgeFromMcpJob ainda não criado pelo Agent A.');
    }

    (new $jobClass(1))->handle();

    // Resultado: kb_nodes só pra biz=1
    expect(\DB::table('kb_nodes')->where('business_id', 1)->count())->toBe(1)
        ->and(\DB::table('kb_nodes')->where('business_id', 99)->count())->toBe(0);
});

it('PUT cross-tenant: user biz=1 NAO pode editar node biz=99 mesmo conhecendo slug', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'shared-slug',
        'title' => 'original biz 99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'biz99 content']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    $response = $this->putJson('/kb/nodes/shared-slug', [
        'title'       => 'HACKED biz 1',
        'body_blocks' => [['kind' => 'para', 'text' => 'I overwrote biz 99 content']],
    ]);

    expect($response->status())->toBeIn([403, 404]);
    $row = \DB::table('kb_nodes')->where('business_id', 99)->where('slug', 'shared-slug')->first();
    expect($row->title)->toBe('original biz 99');  // intocado
});

it('DELETE cross-tenant: user biz=1 NAO pode soft-deletar node biz=99', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'cant-delete',
        'title' => 'biz99 alive', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.softdelete']);

    $response = $this->deleteJson('/kb/nodes/cant-delete');

    expect($response->status())->toBeIn([403, 404]);
    $row = \DB::table('kb_nodes')->where('business_id', 99)->where('slug', 'cant-delete')->first();
    expect($row->deleted_at)->toBeNull();
});
