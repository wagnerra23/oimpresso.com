<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\MeilisearchIndexSetupCommand;

uses(Tests\TestCase::class);

/**
 * jana:meilisearch-setup — config-as-code dos embedders Meilisearch (gap "se perdeu").
 *
 * Blinda que a config canônica (copiloto.meilisearch_indexes) tem os 2 índices com o
 * embedder qwen3_local correto, e que o payload PATCH é montado certo. Não bate no
 * Meilisearch live (isso é o smoke em prod).
 */

it('config canônica tem jana_memoria_facts + mcp_memory_documents com embedder qwen3_local', function () {
    $cfg = config('copiloto.meilisearch_indexes');

    expect($cfg)->toBeArray()
        ->toHaveKeys(['jana_memoria_facts', 'mcp_memory_documents']);

    // ambos usam qwen3_local (venceu nomic no eval Sprint 9 — nomic inútil PT-BR)
    expect($cfg['jana_memoria_facts']['embedders'])->toHaveKey('qwen3_local')
        ->and($cfg['mcp_memory_documents']['embedders'])->toHaveKey('qwen3_local')
        ->and($cfg['jana_memoria_facts']['embedders']['qwen3_local']['source'])->toBe('ollama')
        ->and($cfg['jana_memoria_facts']['embedders']['qwen3_local']['documentTemplate'])->toBe('{{doc.fato}}');
});

it('mcp_memory_documents NÃO filtra business_id (corpus global); jana_memoria_facts SIM', function () {
    $cfg = config('copiloto.meilisearch_indexes');

    expect($cfg['mcp_memory_documents']['filterableAttributes'])->not->toContain('business_id')
        ->and($cfg['jana_memoria_facts']['filterableAttributes'])->toContain('business_id');
});

it('payloadPara monta embedders + filterableAttributes', function () {
    $cmd = new MeilisearchIndexSetupCommand();
    $payload = $cmd->payloadPara([
        'embedders' => ['qwen3_local' => ['source' => 'ollama']],
        'filterableAttributes' => ['business_id', 'user_id'],
    ]);

    expect($payload)->toHaveKeys(['embedders', 'filterableAttributes'])
        ->and($payload['embedders'])->toHaveKey('qwen3_local')
        ->and($payload['filterableAttributes'])->toBe(['business_id', 'user_id']);
});

// ── SettingsReconciler — detectarDrift (o gate "nunca mais perder o embedder") ──

$cfg = [
    'embedders' => ['qwen3_local' => ['source' => 'ollama', 'model' => 'qwen3-embedding:0.6b', 'dimensions' => 1024]],
    'filterableAttributes' => ['status', 'type'],
];

it('detectarDrift: settings vivos == config → sem drift', function () use ($cfg) {
    $vivo = ['embedders' => $cfg['embedders'], 'filterableAttributes' => ['type', 'status']]; // ordem diferente OK
    expect((new MeilisearchIndexSetupCommand())->detectarDrift('x', $cfg, $vivo))->toBeEmpty();
});

it('detectarDrift: embedder VAZIO (o bug recorrente) → drift', function () use ($cfg) {
    $vivo = ['embedders' => [], 'filterableAttributes' => ['status', 'type']];
    $d = (new MeilisearchIndexSetupCommand())->detectarDrift('jana_memoria_facts', $cfg, $vivo);
    expect($d)->not->toBeEmpty()
        ->and($d[0])->toContain("embedder 'qwen3_local' AUSENTE");
});

it('detectarDrift: model divergente (ex openai) → drift', function () use ($cfg) {
    $vivo = ['embedders' => ['qwen3_local' => ['source' => 'ollama', 'model' => 'nomic-embed-text', 'dimensions' => 1024]], 'filterableAttributes' => ['status', 'type']];
    expect((new MeilisearchIndexSetupCommand())->detectarDrift('x', $cfg, $vivo))
        ->toHaveCount(1)
        ->and(implode('', (new MeilisearchIndexSetupCommand())->detectarDrift('x', $cfg, $vivo)))->toContain('.model difere');
});

it('detectarDrift: filterableAttributes diferente → drift', function () use ($cfg) {
    $vivo = ['embedders' => $cfg['embedders'], 'filterableAttributes' => ['status']];
    expect((new MeilisearchIndexSetupCommand())->detectarDrift('x', $cfg, $vivo))
        ->toHaveCount(1)
        ->and(implode('', (new MeilisearchIndexSetupCommand())->detectarDrift('x', $cfg, $vivo)))->toContain('filterableAttributes difere');
});
