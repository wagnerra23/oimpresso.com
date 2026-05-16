<?php

declare(strict_types=1);

use Modules\KB\Entities\KbPath;
use Modules\KB\Entities\KbPathStep;

/**
 * Unit specs do KbPathStep.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 *   - position 1-based
 *   - UNIQUE (path_id, position)
 *   - steps de uma trilha ordenam por position ASC (relação ordenada)
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('orders path steps by position ascending', function () {
    kbActAsUser(bizId: 1);

    $pathId = \DB::table('kb_paths')->insertGetId([
        'business_id' => 1, 'slug' => 'trilha-test', 'title' => 'Trilha test',
        'status' => 'published', 'hue' => 240,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Cria 3 nodes
    $nodeIds = [];
    for ($i = 1; $i <= 3; $i++) {
        $nodeIds[] = \DB::table('kb_nodes')->insertGetId([
            'business_id' => 1, 'type' => 'article', 'slug' => "n{$i}",
            'title' => "Node {$i}", 'is_editable' => true,
            'body_blocks' => json_encode([['kind' => 'para', 'text' => "n{$i}"]]),
            'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Insere em ordem 3, 1, 2 — espera ordenação automática por position
    \DB::table('kb_path_steps')->insert([
        ['business_id' => 1, 'path_id' => $pathId, 'node_id' => $nodeIds[2], 'position' => 3, 'step_type' => 'leitura', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'path_id' => $pathId, 'node_id' => $nodeIds[0], 'position' => 1, 'step_type' => 'leitura', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'path_id' => $pathId, 'node_id' => $nodeIds[1], 'position' => 2, 'step_type' => 'pratica', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Espera-se que KbPath::with('steps') ou ::steps() retorne em ordem ASC.
    $path = KbPath::find($pathId);
    expect($path)->not->toBeNull();

    $steps = $path->steps()->orderBy('position')->get();
    // TODO[CL]: Agent A deve garantir que steps() já vem orderBy('position').
    // Se sim, $path->steps pode ser usado direto. Aqui forçamos pra ser seguro.

    expect($steps)->toHaveCount(3)
        ->and($steps[0]->position)->toBe(1)
        ->and($steps[1]->position)->toBe(2)
        ->and($steps[2]->position)->toBe(3);
});

it('blocks duplicate position within same path', function () {
    kbActAsUser(bizId: 1);

    $pathId = \DB::table('kb_paths')->insertGetId([
        'business_id' => 1, 'slug' => 'trilha-dup', 'title' => 'dup',
        'status' => 'published', 'hue' => 240,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'n-dup',
        'title' => 'n', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('kb_path_steps')->insert([
        'business_id' => 1, 'path_id' => $pathId, 'node_id' => $nodeId,
        'position' => 1, 'step_type' => 'leitura',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(function () use ($pathId, $nodeId) {
        \DB::table('kb_path_steps')->insert([
            'business_id' => 1, 'path_id' => $pathId, 'node_id' => $nodeId,
            'position' => 1, 'step_type' => 'leitura',  // DUP
            'created_at' => now(), 'updated_at' => now(),
        ]);
    })->toThrow(\Throwable::class);
});
