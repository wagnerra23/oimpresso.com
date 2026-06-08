<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Officeimpresso.
 *
 * Garante que:
 *   1. Modulo aparece registrado em nWidart Module::find()
 *   2. ServiceProvider carrega sem erro (Module::find retorna instancia ativa)
 *   3. Rotas web nomeadas principais foram registradas via Route::has()
 *
 * Officeimpresso é bridge legacy Delphi WR Sistemas → oimpresso Laravel.
 *
 * Refs: ADR 0011 padrao Jana/Repair/Project, skill criar-modulo, ADR 0024.
 *
 * @see Modules/Officeimpresso/module.json (alias=officeimpresso, active=1)
 */

it('cenario 1: modulo Officeimpresso aparece registrado em nWidart', function () {
    $module = Module::find('Officeimpresso');
    expect($module)->not->toBeNull('Modules/Officeimpresso deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Officeimpresso');
});

it('cenario 2: modulo Officeimpresso esta ativo (active=1 em module.json)', function () {
    $module = Module::find('Officeimpresso');
    expect($module->isEnabled())->toBeTrue('Modules/Officeimpresso deveria estar ativo');
});

it('cenario 3: rota licenca_computador.index existe', function () {
    expect(Route::has('licenca_computador.index'))
        ->toBeTrue('Rota licenca_computador.index deveria existir per Routes/web.php (Route::resource)');
});

it('cenario 4: rota officeimpresso.catalogue existe', function () {
    expect(Route::has('officeimpresso.catalogue'))
        ->toBeTrue('Rota officeimpresso.catalogue deveria existir');
});

it('cenario 5: rota officeimpresso.install existe', function () {
    expect(Route::has('officeimpresso.install'))
        ->toBeTrue('Rota officeimpresso.install deveria existir (botao Install no superadmin)');
});

it('cenario 6: rota officeimpresso.install.post existe', function () {
    expect(Route::has('officeimpresso.install.post'))
        ->toBeTrue('Rota officeimpresso.install.post deveria existir (3 rotas Install — skill criar-modulo)');
});

it('cenario 7: rota officeimpresso.install.uninstall existe', function () {
    expect(Route::has('officeimpresso.install.uninstall'))
        ->toBeTrue('Rota officeimpresso.install.uninstall deveria existir');
});

it('cenario 8: rota computadores existe', function () {
    expect(Route::has('computadores'))
        ->toBeTrue('Rota computadores deveria existir');
});

it('cenario 9: rota licenca_log.index existe', function () {
    expect(Route::has('licenca_log.index'))
        ->toBeTrue('Rota licenca_log.index deveria existir per Routes/web.php (Route::resource)');
});

it('cenario 10: rota licenca_log.timeline existe', function () {
    expect(Route::has('licenca_log.timeline'))
        ->toBeTrue('Rota licenca_log.timeline existe pra UI append-only de eventos');
});
