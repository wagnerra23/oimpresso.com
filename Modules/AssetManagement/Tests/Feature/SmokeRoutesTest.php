<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais do Modules/AssetManagement.
 *
 * Valida que as rotas resource declaradas em Routes/web.php foram registradas
 * pelo nWidart/laravel-modules e estão acessíveis via Route::has().
 *
 * Refs: Routes/web.php (Route::resource assets/allocation/revocation/settings/asset-maintenance)
 * Padrão: skill `criar-modulo` + Modules/Auditoria/Tests/Feature/AuditoriaModuleTest.php
 */

it('cenario 1: rota nomeada assets.index existe', function () {
    expect(\Route::has('assets.index'))->toBeTrue('Rota assets.index deveria existir per Routes/web.php');
});

it('cenario 2: rota nomeada assets.create existe', function () {
    expect(\Route::has('assets.create'))->toBeTrue('Rota assets.create deveria existir');
});

it('cenario 3: rota nomeada allocation.index existe', function () {
    expect(\Route::has('allocation.index'))->toBeTrue('Rota allocation.index deveria existir');
});

it('cenario 4: rota nomeada asset-maintenance.index existe', function () {
    expect(\Route::has('asset-maintenance.index'))->toBeTrue('Rota asset-maintenance.index deveria existir');
});
