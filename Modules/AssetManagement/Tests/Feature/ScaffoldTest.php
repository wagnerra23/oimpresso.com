<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/AssetManagement.
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart (module.json carregado)
 *   2. ServiceProvider AssetManagementServiceProvider carregou sem erro
 *   3. Rotas resource principais (assets + allocation + asset-maintenance) registradas
 *
 * Refs: module.json (provider AssetManagementServiceProvider), ADR 0011 padrão Jana/Repair/Project
 */

it('cenario 1: modulo AssetManagement aparece registrado em nWidart', function () {
    $module = Module::find('AssetManagement');
    expect($module)->not->toBeNull('Modules/AssetManagement deveria estar registrado em nWidart');
    expect($module->getName())->toBe('AssetManagement');
});

it('cenario 2: modulo AssetManagement esta ativo (module.json active=1)', function () {
    $module = Module::find('AssetManagement');
    expect($module)->not->toBeNull();
    expect($module->isEnabled())->toBeTrue('AssetManagement deveria estar habilitado per module.json');
});

it('cenario 3: rotas resource principais registradas (assets/allocation/asset-maintenance index)', function () {
    expect(\Route::has('assets.index'))->toBeTrue('assets.index deveria existir');
    expect(\Route::has('allocation.index'))->toBeTrue('allocation.index deveria existir');
    expect(\Route::has('asset-maintenance.index'))->toBeTrue('asset-maintenance.index deveria existir');
});

it('cenario 4: rota resource revocation.index registrada', function () {
    expect(\Route::has('revocation.index'))->toBeTrue('revocation.index deveria existir per Route::resource em Routes/web.php');
});
