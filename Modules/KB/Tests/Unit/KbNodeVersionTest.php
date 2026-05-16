<?php

declare(strict_types=1);

use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;

/**
 * Unit specs do KbNodeVersion.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §8
 *
 * Invariantes append-only (KbNodeVersionObserver):
 *   - UPDATE bloqueado (Exception)
 *   - DELETE bloqueado (Exception)
 *   - SÓ populado pra kb_nodes.is_editable=true (bridge canon NUNCA tem version)
 *
 * Trigger MySQL append-only fica V2 — V1 confia em Observer (ADR 0061).
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('rejects UPDATE on existing version (append-only)', function () {
    kbActAsUser(bizId: 1);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'v-node',
        'title' => 'Version test', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'v1']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $version = new KbNodeVersion();
    $version->business_id    = 1;
    $version->node_id        = $nodeId;
    $version->version_at     = now();
    $version->snapshot       = ['title' => 'snap', 'body_blocks' => []];
    $version->change_reason  = 'edit 1';
    $version->save();

    expect(function () use ($version) {
        $version->change_reason = 'tentativa de UPDATE';
        $version->save();
    })->toThrow(\Throwable::class);
});

it('rejects DELETE on existing version (append-only)', function () {
    kbActAsUser(bizId: 1);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'v-node-del',
        'title' => 'Version del test', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'v1']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $version = new KbNodeVersion();
    $version->business_id   = 1;
    $version->node_id       = $nodeId;
    $version->version_at    = now();
    $version->snapshot      = ['title' => 'snap'];
    $version->change_reason = 'created';
    $version->save();

    expect(fn () => $version->delete())->toThrow(\Throwable::class);
});

it('only creates version for editable nodes (not bridge canon)', function () {
    // Bridge node (is_editable=false) NÃO deve gerar version.
    // Esta regra é enforced no Service que orquestra edit (Modules\KB\Services\KbNodeEditor
    // ou similar). O Observer pode também detectar e bloquear.
    //
    // TODO[CL]: Agent A define se enforce é no Service OU no Observer.
    // Aqui assertamos que tentativa direta de versionar bridge node FALHA.

    kbActAsUser(bizId: 1);
    $mcpId = kbCreateMcpDoc(1, 'adr');

    $bridgeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'adr', 'slug' => 'adr-0094',
        'title' => 'ADR 0094 bridge', 'is_editable' => false,
        'body_blocks' => null,
        'source_doc_id' => $mcpId,
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(function () use ($bridgeId) {
        $version = new KbNodeVersion();
        $version->business_id    = 1;
        $version->node_id        = $bridgeId;
        $version->version_at     = now();
        $version->snapshot       = ['title' => 'WRONG'];
        $version->change_reason  = 'should fail';
        $version->save();
    })->toThrow(\Throwable::class);
});

it('persists snapshot as JSON array', function () {
    kbActAsUser(bizId: 1);
    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'jsoncast',
        'title' => 'json cast', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $v = new KbNodeVersion();
    $v->business_id    = 1;
    $v->node_id        = $nodeId;
    $v->version_at     = now();
    $v->snapshot       = ['title' => 'A', 'tags' => ['x']];
    $v->change_reason  = 'create';
    $v->save();

    $fresh = KbNodeVersion::where('id', $v->id)->first();
    expect($fresh->snapshot)->toBeArray()
        ->and($fresh->snapshot)->toBe(['title' => 'A', 'tags' => ['x']]);
});

it('scopes by business_id', function () {
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);

    // Pra criar 1 node em cada biz precisamos burlar global scope.
    $node1 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'n-biz1',
        'title' => 'n1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $node99 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 99, 'type' => 'article', 'slug' => 'n-biz99',
        'title' => 'n99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('kb_node_versions')->insert([
        ['business_id' => 1, 'node_id' => $node1, 'version_at' => now(), 'snapshot' => json_encode(['t' => 'biz1'])],
        ['business_id' => 99, 'node_id' => $node99, 'version_at' => now(), 'snapshot' => json_encode(['t' => 'biz99'])],
    ]);

    kbActAsUser(bizId: 1);
    $versions = KbNodeVersion::all();
    expect($versions)->toHaveCount(1)
        ->and($versions->first()->node_id)->toBe($node1);
});
