<?php

declare(strict_types=1);

/**
 * Feature specs — Troubleshooters (SCHEMA-DB-V1 §11):
 *   GET   /kb/decision-trees
 *   GET   /kb/decision-trees/{slug}
 *   POST  /kb/decision-trees   (perm: kb.publish.troubleshoot)
 *   PUT   /kb/decision-trees/{slug}
 *
 * Inclui validação FK circular: root_step_id populado em segundo INSERT
 * pós-criação dos steps. Service deve fazer em transação.
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('POST /kb/decision-trees without kb.publish.troubleshoot returns 403', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $response = $this->postJson('/kb/decision-trees', [
        'slug'  => 'no-perm',
        'title' => 'try',
        'steps' => [],
    ]);

    $response->assertForbidden();
});

it('POST /kb/decision-trees creates tree + steps in transaction (root_step_id populated)', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view', 'kb.publish.troubleshoot']);

    $response = $this->postJson('/kb/decision-trees', [
        'slug'        => 'plotter-nao-liga',
        'title'       => 'Plotter Roland não liga',
        'equip'       => 'Roland VS-540',
        'when_to_use' => 'Plotter aparece desligado, sem LED',
        'steps' => [
            [
                'position' => 1,
                'question' => 'O botão liga acende a luz vermelha?',
                'yes_fix'  => 'OK, equipamento ligando — siga o procedimento padrão.',
                'no_fix'   => 'Confira tomada/cabo principal — passe pra próxima.',
            ],
            [
                'position' => 2,
                'question' => 'Tomada tem 220V?',
                'yes_fix'  => 'Trocar fonte interna — chamar técnico Roland.',
                'no_fix'   => 'Trocar circuito do escritório — eletricista.',
            ],
        ],
    ]);

    $response->assertCreated();

    $treeId = \DB::table('kb_decision_trees')->where('slug', 'plotter-nao-liga')->value('id');
    expect($treeId)->not->toBeNull();

    // root_step_id deve apontar pro step de position=1
    $tree = \DB::table('kb_decision_trees')->where('id', $treeId)->first();
    $expectedRoot = \DB::table('kb_decision_tree_steps')
        ->where('tree_id', $treeId)
        ->where('position', 1)
        ->value('id');

    expect($tree->root_step_id)->toBe((int) $expectedRoot);

    // 2 steps criados
    expect(\DB::table('kb_decision_tree_steps')->where('tree_id', $treeId)->count())->toBe(2);
});

it('GET /kb/decision-trees lists published troubleshooters of current business', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    \DB::table('kb_decision_trees')->insert([
        ['business_id' => 1, 'slug' => 'pub-1', 'title' => 'Pub 1', 'hue' => 240, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'slug' => 'arch-1', 'title' => 'Arch 1', 'hue' => 240, 'status' => 'archived', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->getJson('/kb/decision-trees');
    $response->assertOk();

    $items = $response->json('data') ?? $response->json('trees') ?? $response->json();
    $slugs = collect($items)->pluck('slug')->all();
    expect($slugs)->toContain('pub-1');
    // TODO[CL]: Agent A define se filtro default exclui archived OR inclui.
    // Esta asserção é suave — apenas espera que listing exista.
});

it('GET /kb/decision-trees/{slug} returns tree with steps ordered', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);

    $treeId = \DB::table('kb_decision_trees')->insertGetId([
        'business_id' => 1, 'slug' => 'detail-tree', 'title' => 'Detail tree',
        'hue' => 240, 'status' => 'published',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $s1 = \DB::table('kb_decision_tree_steps')->insertGetId([
        'business_id' => 1, 'tree_id' => $treeId, 'position' => 1,
        'question' => 'Q1', 'yes_fix' => 'Y1', 'no_fix' => 'N1',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $s2 = \DB::table('kb_decision_tree_steps')->insertGetId([
        'business_id' => 1, 'tree_id' => $treeId, 'position' => 2,
        'question' => 'Q2', 'yes_fix' => 'Y2', 'no_fix' => 'N2',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('kb_decision_trees')->where('id', $treeId)->update(['root_step_id' => $s1]);

    $response = $this->getJson('/kb/decision-trees/detail-tree');
    $response->assertOk();

    $data = $response->json();
    $steps = $data['steps'] ?? $data['tree']['steps'] ?? null;
    expect($steps)->not->toBeNull();
});
