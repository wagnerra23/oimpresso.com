<?php

declare(strict_types=1);

/**
 * Feature specs — Trilhas (SCHEMA-DB-V1 §11):
 *   GET   /kb/paths
 *   GET   /kb/paths/{slug}
 *   POST  /kb/paths   (perm: kb.publish.path)
 *   PUT   /kb/paths/{slug}  (perm: kb.publish.path)
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('GET /kb/paths lists trilhas of current business', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    \DB::table('kb_paths')->insert([
        ['business_id' => 1, 'slug' => 'wagner-onboard', 'title' => 'Wagner onboarding governança',
         'status' => 'published', 'hue' => 240, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'slug' => 'larissa-rotina', 'title' => 'Larissa rotina balcão',
         'status' => 'published', 'hue' => 200, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/kb/paths');
    $response->assertOk();

    $items = $response->json('data') ?? $response->json('paths') ?? $response->json();
    expect(collect($items))->toHaveCount(2)
        ->and(collect($items)->pluck('slug')->all())
        ->toContain('wagner-onboard')
        ->toContain('larissa-rotina');
});

it('POST /kb/paths without kb.publish.path returns 403', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $response = $this->postJson('/kb/paths', [
        'slug'  => 'nao-autorizado',
        'title' => 'try create',
    ]);

    $response->assertForbidden();
    expect(\DB::table('kb_paths')->count())->toBe(0);
});

it('POST /kb/paths with kb.publish.path creates trilha + ordered steps', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.publish.path']);

    // Cria 3 nodes pra serem passos
    $n1 = \DB::table('kb_nodes')->insertGetId(['business_id' => 1, 'type' => 'article', 'slug' => 'p1', 'title' => 'p1', 'is_editable' => true, 'body_blocks' => json_encode([]), 'status' => 'ok', 'created_at' => now(), 'updated_at' => now()]);
    $n2 = \DB::table('kb_nodes')->insertGetId(['business_id' => 1, 'type' => 'article', 'slug' => 'p2', 'title' => 'p2', 'is_editable' => true, 'body_blocks' => json_encode([]), 'status' => 'ok', 'created_at' => now(), 'updated_at' => now()]);
    $n3 = \DB::table('kb_nodes')->insertGetId(['business_id' => 1, 'type' => 'article', 'slug' => 'p3', 'title' => 'p3', 'is_editable' => true, 'body_blocks' => json_encode([]), 'status' => 'ok', 'created_at' => now(), 'updated_at' => now()]);

    $response = $this->postJson('/kb/paths', [
        'slug'  => 'trilha-nova',
        'title' => 'Como funciona X',
        'audience' => 'Wagner',
        'steps' => [
            ['node_id' => $n1, 'position' => 1, 'step_type' => 'leitura'],
            ['node_id' => $n2, 'position' => 2, 'step_type' => 'pratica'],
            ['node_id' => $n3, 'position' => 3, 'step_type' => 'decisao'],
        ],
    ]);

    $response->assertCreated();
    $pathId = \DB::table('kb_paths')->where('slug', 'trilha-nova')->value('id');
    expect($pathId)->not->toBeNull()
        ->and(\DB::table('kb_path_steps')->where('path_id', $pathId)->count())->toBe(3);

    // Steps em ordem
    $stepNodeIds = \DB::table('kb_path_steps')->where('path_id', $pathId)->orderBy('position')->pluck('node_id')->all();
    expect($stepNodeIds)->toBe([$n1, $n2, $n3]);
});

it('GET /kb/paths/{slug} returns trilha detail with steps ordered', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $pathId = \DB::table('kb_paths')->insertGetId([
        'business_id' => 1, 'slug' => 'detail-trilha', 'title' => 't',
        'status' => 'published', 'hue' => 240,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $n1 = \DB::table('kb_nodes')->insertGetId(['business_id' => 1, 'type' => 'article', 'slug' => 'dt1', 'title' => '1', 'is_editable' => true, 'body_blocks' => json_encode([]), 'status' => 'ok', 'created_at' => now(), 'updated_at' => now()]);
    $n2 = \DB::table('kb_nodes')->insertGetId(['business_id' => 1, 'type' => 'article', 'slug' => 'dt2', 'title' => '2', 'is_editable' => true, 'body_blocks' => json_encode([]), 'status' => 'ok', 'created_at' => now(), 'updated_at' => now()]);

    \DB::table('kb_path_steps')->insert([
        ['business_id' => 1, 'path_id' => $pathId, 'node_id' => $n2, 'position' => 2, 'step_type' => 'leitura', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'path_id' => $pathId, 'node_id' => $n1, 'position' => 1, 'step_type' => 'leitura', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/kb/paths/detail-trilha');
    $response->assertOk();

    $data = $response->json();
    // Aceita shape com 'steps' top-level OR aninhado
    $steps = $data['steps'] ?? $data['path']['steps'] ?? null;
    expect($steps)->not->toBeNull();
    $positions = collect($steps)->pluck('position')->all();
    expect($positions)->toBe([1, 2]);
});
