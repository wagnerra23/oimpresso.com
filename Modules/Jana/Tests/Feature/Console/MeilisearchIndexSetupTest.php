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

it('--index com nome inexistente FALHA em vez de retornar SUCCESS silencioso', function () {
    // Revisão adversarial 2026-05-29 (finding #6): --index=typo pulava todos os
    // índices no loop e caía no return SUCCESS — no-op silencioso mascarava o erro.
    $this->artisan('jana:meilisearch-setup', ['--index' => 'indice_que_nao_existe', '--dry-run' => true])
        ->assertExitCode(1);
});

// NOTA: a detecção de drift (settings vivos × config) NÃO mora mais aqui — virou
// MeilisearchSettingsDriftChecker (Modules/Governance, ADR 0216). Ver
// Modules/Governance/Tests/.../MeilisearchSettingsDriftCheckerTest.php.
