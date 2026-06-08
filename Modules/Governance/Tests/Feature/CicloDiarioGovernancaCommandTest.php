<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * `governanca:ciclo-diario` — orquestrador diário (advisory).
 *
 * Cobre: o ciclo roda sem quebrar, escreve state.json + digest.md com o shape
 * canônico, e é advisory (exit 0 sempre). Não testa o cron (Kernel) — isso é
 * smoke de schedule, fora de escopo aqui.
 *
 * @see Modules/Governance/Console/Commands/CicloDiarioGovernancaCommand.php
 */
beforeEach(function () {
    foreach (['governanca-state.json', 'governanca-digest.md'] as $f) {
        $p = storage_path("reports/{$f}");
        if (file_exists($p)) {
            unlink($p);
        }
    }
});

it('governanca:ciclo-diario roda advisory (exit 0) e escreve state + digest', function () {
    $this->artisan('governanca:ciclo-diario')->assertExitCode(0);

    expect(file_exists(storage_path('reports/governanca-state.json')))->toBeTrue();
    expect(file_exists(storage_path('reports/governanca-digest.md')))->toBeTrue();
});

it('state.json tem o shape canônico (generator, frescor, inbox, espera_w)', function () {
    $this->artisan('governanca:ciclo-diario --json')->assertExitCode(0);

    $state = json_decode((string) file_get_contents(storage_path('reports/governanca-state.json')), true);

    expect($state)->toBeArray();
    expect($state['generator'])->toBe('governanca:ciclo-diario');
    expect($state)->toHaveKeys(['date', 'measured_against_sha', 'aprovado', 'frescor', 'inbox', 'espera_w']);
    expect($state['frescor'])->toHaveKeys(['graduacao', 'acendeu']);
    expect($state['inbox'])->toHaveKeys(['total', 'graduadas', 'pendentes', 'pendentes_ids']);
    expect($state['espera_w'])->toBeArray();
});

it('o digest é legível e cita as 4 linhas do resumo (Graduou/Acendeu/Inbox/Espera)', function () {
    $this->artisan('governanca:ciclo-diario')->assertExitCode(0);

    $digest = (string) file_get_contents(storage_path('reports/governanca-digest.md'));

    expect($digest)->toContain('Digest diário');
    expect($digest)->toContain('Graduou:');
    expect($digest)->toContain('Acendeu (advisory):');
    expect($digest)->toContain('Inbox [W]:');
    expect($digest)->toContain('Espera [W] (Tier 0):');
});

it('reusa o scorecard (ponte main #2151): graduação dos 2 ledgers aparece no state', function () {
    $this->artisan('governanca:ciclo-diario --json')->assertExitCode(0);

    $state = json_decode((string) file_get_contents(storage_path('reports/governanca-state.json')), true);

    // Os ledgers reais do repo (operacao + cc) graduam; o ciclo reflete o ratio.
    expect($state['frescor']['graduacao'])->toBeArray();
    // pelo menos um ledger presente → a chave de graduação não é vazia em main.
    expect(count($state['frescor']['graduacao']))->toBeGreaterThanOrEqual(1);
});
