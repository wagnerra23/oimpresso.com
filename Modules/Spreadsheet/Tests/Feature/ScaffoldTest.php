<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Spreadsheet (US-SHEET-001 — gap P3).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart (module.json válido).
 *   2. ServiceProvider carrega sem erro de boot.
 *   3. Rotas web foram registradas (via Route::has nas rotas resource).
 *
 * Refs:
 *   - ADR 0011 (padrão Jana/Repair/Project)
 *   - skill `criar-modulo` (8 peças obrigatórias)
 *   - memory/requisitos/Infra/RUNBOOK-criar-modulo.md
 */

it('cenário 1: módulo Spreadsheet aparece registrado em nWidart', function () {
    $module = Module::find('Spreadsheet');

    expect($module)->not->toBeNull('Modules/Spreadsheet deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Spreadsheet');
});

it('cenário 2: módulo Spreadsheet está ativo (active=1 no module.json)', function () {
    $module = Module::find('Spreadsheet');

    expect($module)->not->toBeNull();
    expect($module->isEnabled())->toBeTrue('Spreadsheet deveria estar enabled (active=1)');
});

it('cenário 3: rota nomeada sheets.index existe (Route::resource auto-name)', function () {
    expect(\Route::has('sheets.index'))->toBeTrue(
        'Route::resource(sheets, SpreadsheetController) deveria gerar sheets.index'
    );
});

it('cenário 4: rota nomeada sheets.destroy existe (Route::resource auto-name)', function () {
    expect(\Route::has('sheets.destroy'))->toBeTrue(
        'Route::resource(sheets) deveria gerar sheets.destroy'
    );
});

it('cenário 5: ServiceProvider Spreadsheet está carregado no container Laravel', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(\Modules\Spreadsheet\Providers\SpreadsheetServiceProvider::class);
});
