<?php

declare(strict_types=1);

beforeEach(function () {
    config(['evolution.memory_path' => base_path('memory')]);
});

/*
|--------------------------------------------------------------------------
| Fase 1b — rank command (lê SPEC.md, extrai tabelas ROI)
|--------------------------------------------------------------------------
| Ver: memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-003
*/

it('evolution:rank registrado no artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('evolution:rank')
        ->assertExitCode(0);
});

it('rank --escopo=EvolutionAgent extrai tabela ROI do SPEC.md', function () {
    $this->artisan('evolution:rank', [
        '--escopo' => 'EvolutionAgent',
        '--top' => 3,
    ])
        ->assertExitCode(0);
});

it('rank --json devolve estrutura serializável', function () {
    $exit = $this->artisan('evolution:rank', [
        '--escopo' => 'EvolutionAgent',
        '--json' => true,
    ])->run();

    expect($exit)->toBe(0);
});
