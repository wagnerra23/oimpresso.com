<?php

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Auditoria (US-AUDIT-007).
 *
 * Garante que:
 *   1. Modulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas web /auditoria foram registradas
 *
 * Refs: ADR 0127 §F3, ADR 0011 padrao Jana/Repair/Project, skill criar-modulo
 */

it('cenario 1: modulo Auditoria aparece registrado em nWidart', function () {
    $module = Module::find('Auditoria');
    expect($module)->not->toBeNull('Modules/Auditoria deveria estar registrado');
    expect($module->getName())->toBe('Auditoria');
});

it('cenario 2: rota nomeada auditoria.index existe', function () {
    expect(\Route::has('auditoria.index'))->toBeTrue('Rota auditoria.index deveria existir per Routes/web.php');
});

it('cenario 3: rota nomeada auditoria.show existe', function () {
    expect(\Route::has('auditoria.show'))->toBeTrue('Rota auditoria.show deveria existir');
});

it('cenario 4: rota nomeada auditoria.revert existe', function () {
    expect(\Route::has('auditoria.revert'))->toBeTrue('Rota auditoria.revert deveria existir');
});
