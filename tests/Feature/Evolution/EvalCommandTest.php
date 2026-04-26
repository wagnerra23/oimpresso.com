<?php

declare(strict_types=1);

use App\Services\Evolution\Eval\GoldenSetRunner;
use Tests\Feature\Evolution\InitVizraSchema;

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ],
    ]);
    \DB::purge('sqlite');
    InitVizraSchema::run();
});

afterEach(function () {
    InitVizraSchema::tearDown();
});

/*
|--------------------------------------------------------------------------
| Fase 1b — eval command (golden set + LLM-as-judge offline)
|--------------------------------------------------------------------------
| Ver: memory/requisitos/EvolutionAgent/TESTS.md T-021
*/

it('evolution:eval registrado no artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('evolution:eval')
        ->assertExitCode(0);
});

it('GoldenSetRunner carrega 5 casos do JSON', function () {
    $runner = new GoldenSetRunner(
        goldenSetPath: base_path('tests/Evolution/golden_set.json'),
    );

    $report = $runner->run();

    expect($report['count'])->toBe(5)
        ->and($report['score_avg'])->toBeFloat()
        ->and($report['score_avg'])->toBeBetween(0, 5)
        ->and($report['results'])->toBeArray()
        ->and($report['results'][0])->toHaveKeys(['id', 'pergunta', 'resposta', 'score']);
});

it('eval modo offline (sem ANTHROPIC_API_KEY) não falha', function () {
    config(['prism.providers.anthropic.api_key' => '']);

    $exit = $this->artisan('evolution:eval', ['--json' => true])->run();

    expect($exit)->toBeIn([0, 1]); // 0 ok, 1 só se baseline regrediu
});
