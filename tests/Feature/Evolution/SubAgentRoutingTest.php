<?php

declare(strict_types=1);

use App\Services\Evolution\Agents\EvolutionAgent;
use App\Services\Evolution\Agents\FinanceiroAgent;
use Tests\Feature\Evolution\InitVizraSchema;

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ],
        'evolution.memory_path' => base_path('tests/fixtures/memory-fake'),
        'prism.providers.anthropic.api_key' => '', // força modo offline em todos os testes
    ]);
    \DB::purge('sqlite');
    InitVizraSchema::run();
});

afterEach(function () {
    InitVizraSchema::tearDown();
});

it('EvolutionAgent system prompt cita meta R$5mi e ADR 0026', function () {
    $agent = new EvolutionAgent;

    expect($agent->getSystemPrompt())
        ->toContain('R$ 5mi')
        ->toContain('ADR 0026');
});

it('FinanceiroAgent system prompt cita Onda 1+2 + backfill', function () {
    $agent = new FinanceiroAgent;

    $prompt = $agent->getSystemPrompt();

    expect($prompt)
        ->toContain('Financeiro')
        ->toContain('Onda 1')
        ->toContain('Onda 2')
        ->toContain('backfill');
});

it('FinanceiroAgent tem scope=Financeiro pra filtrar MemoryQuery', function () {
    $agent = new FinanceiroAgent;
    expect($agent->getScope())->toBe('Financeiro');
});

it('EvolutionAgent registra as 8 tools', function () {
    $agent = new EvolutionAgent;
    $names = array_keys($agent->getTools());

    expect($names)->toContain('MemoryQuery')
        ->toContain('ListAdrs')
        ->toContain('RankByRoi')
        ->toContain('PestRun')
        ->toContain('RouteList')
        ->toContain('ModelSchema')
        ->toContain('GitDiffStat')
        ->toContain('EvalGoldenSet');
});

it('FinanceiroAgent.run em modo offline retorna texto + traces sem chamar API', function () {
    $agent = new FinanceiroAgent;
    $response = $agent->run('próximo passo Financeiro');

    expect($response->text)->toBeString()->not->toBeEmpty()
        ->and($response->traces)->toBeArray()
        ->and(count($response->traces))->toBeGreaterThanOrEqual(1)
        ->and($response->traces[0]['tool'])->toBe('MemoryQuery');
});
