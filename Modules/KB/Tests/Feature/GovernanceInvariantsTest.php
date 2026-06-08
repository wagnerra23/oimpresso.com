<?php

declare(strict_types=1);

use Modules\KB\Entities\KbNode;

/**
 * Governance invariants — Tier 0 IRREVOGÁVEL.
 *
 * ADR 0061 + ADR 0149 (R1):
 *   - ADR canon append-only via bridge
 *   - kb_nodes.body_blocks IS NULL pra type=adr (bridge); conteúdo vem do JOIN
 *   - kb_node_versions NÃO existe pra bridge nodes (versionamento via
 *     mcp_memory_documents_history, não local)
 *
 * Estes tests garantem que governance NÃO SE PERDE:
 *   - mesmo se Controller tenta forçar body_blocks em ADR bridge → falha
 *   - mesmo se Service tenta versionar bridge → falha
 *   - mesmo via HTTP PUT direto → 422 ou rejected
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
});

afterEach(function () {
    kbTeardownSchema();
});

it('keeps ADR bridge nodes body_blocks always NULL (R1 invariante)', function () {
    $mcpId = kbCreateMcpDoc(1, 'adr', [
        'slug' => '0093-multi-tenant',
        'title' => 'ADR 0093',
        'content_md' => '# ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL',
    ]);

    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => '0093',
        'title' => 'ADR 0093', 'is_editable' => false, 'body_blocks' => null,
        'source_doc_id' => $mcpId,
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    // Tenta forçar body_blocks via PUT no slug do ADR bridge
    $response = $this->putJson('/kb/nodes/0093', [
        'body_blocks' => [['kind' => 'para', 'text' => 'CONTEUDO LOCAL ILEGAL']],
    ]);

    // Deve falhar 422 (ValidationException) ou 403 (FormRequest authorize false)
    expect($response->status())->toBeIn([403, 422]);

    // Confirma que body_blocks continua NULL no DB
    $row = \DB::table('kb_nodes')->where('slug', '0093')->first();
    expect($row->body_blocks)->toBeNull();
});

it('prevents kb_node_versions creation for bridge nodes (governance gate)', function () {
    $mcpId = kbCreateMcpDoc(1, 'session');
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'session', 'slug' => 'sess-1',
        'title' => 'Session 1', 'is_editable' => false, 'body_blocks' => null,
        'source_doc_id' => $mcpId,
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $bridgeId = \DB::table('kb_nodes')->where('slug', 'sess-1')->value('id');

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    // Tentar via Model direto
    expect(function () use ($bridgeId) {
        $v = new \Modules\KB\Entities\KbNodeVersion();
        $v->business_id = 1;
        $v->node_id     = $bridgeId;
        $v->version_at  = now();
        $v->snapshot    = ['title' => 'WRONG VERSION', 'body_blocks' => []];
        $v->save();
    })->toThrow(\Throwable::class);
});

it('PUT on bridge ADR with valid editable-only fields (status reverify) still ok', function () {
    // Operações que NÃO ofendem a invariante: re-verificar frescor, zerar
    // outdated_votes, alterar pinned — esses são metadata, não conteúdo.
    $mcpId = kbCreateMcpDoc(1, 'adr', ['slug' => 'verify-me', 'title' => 'ADR']);

    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'adr', 'slug' => 'verify-me',
        'title' => 'ADR Verify', 'is_editable' => false, 'body_blocks' => null,
        'source_doc_id' => $mcpId,
        'status' => 'ok', 'outdated_votes' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.write']);

    $response = $this->postJson('/kb/nodes/verify-me/reverify');
    $response->assertOk();

    $row = \DB::table('kb_nodes')->where('slug', 'verify-me')->first();
    expect((int) $row->outdated_votes)->toBe(0);
});
