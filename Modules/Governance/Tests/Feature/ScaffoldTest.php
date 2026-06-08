<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke scaffold pra Modules/Governance.
 *
 * Garante que:
 *   1. nWidart enxerga o módulo registrado
 *   2. GovernanceServiceProvider carrega sem erro (classe existe + provides)
 *   3. As 7 rotas nomeadas em Http/routes.php existem
 *   4. Os 6 Controllers existem (DashboardController + 5 outros)
 *   5. ActionGate middleware foi aliasado pra `actiongate`
 *
 * Refs: ADR 0011 padrão Jana/Repair, ADR 0024 InstallController, ADR 0086 MVP Governance UI.
 */
it('cenario 1: modulo Governance esta registrado em nWidart', function () {
    $module = Module::find('Governance');
    expect($module)->not->toBeNull('Modules/Governance deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Governance');
});

it('cenario 2: GovernanceServiceProvider existe e e carregavel', function () {
    expect(class_exists(\Modules\Governance\Providers\GovernanceServiceProvider::class))
        ->toBeTrue('ServiceProvider canonico deveria existir');
});

it('cenario 3: rota nomeada governance.admin.dashboard existe', function () {
    expect(\Route::has('governance.admin.dashboard'))
        ->toBeTrue('Rota governance.admin.dashboard deveria existir (DashboardController@index)');
});

it('cenario 4: rota nomeada governance.policies.index existe', function () {
    expect(\Route::has('governance.policies.index'))
        ->toBeTrue('Rota governance.policies.index deveria existir');
});

it('cenario 5: rota nomeada governance.audit.index existe', function () {
    expect(\Route::has('governance.audit.index'))
        ->toBeTrue('Rota governance.audit.index deveria existir (AuditController@index)');
});

it('cenario 6: rota nomeada governance.drift.index existe', function () {
    expect(\Route::has('governance.drift.index'))
        ->toBeTrue('Rota governance.drift.index deveria existir (DriftAlertsController@index)');
});

it('cenario 7: rota Install nomeada governance.install.index existe', function () {
    expect(\Route::has('governance.install.index'))
        ->toBeTrue('Rota governance.install.index deveria existir (ADR 0024)');
});

it('cenario 8: rota Install uninstall existe (ADR 0024 padrao)', function () {
    expect(\Route::has('governance.install.uninstall'))
        ->toBeTrue('Rota governance.install.uninstall deveria existir');
});

it('cenario 9: rota Install update existe (ADR 0024 padrao)', function () {
    expect(\Route::has('governance.install.update'))
        ->toBeTrue('Rota governance.install.update deveria existir');
});

it('cenario 10: os 6 Controllers existem', function () {
    expect(class_exists(\Modules\Governance\Http\Controllers\DashboardController::class))->toBeTrue();
    expect(class_exists(\Modules\Governance\Http\Controllers\PoliciesController::class))->toBeTrue();
    expect(class_exists(\Modules\Governance\Http\Controllers\AuditController::class))->toBeTrue();
    expect(class_exists(\Modules\Governance\Http\Controllers\DriftAlertsController::class))->toBeTrue();
    expect(class_exists(\Modules\Governance\Http\Controllers\InstallController::class))->toBeTrue();
    expect(class_exists(\Modules\Governance\Http\Controllers\DataController::class))->toBeTrue();
});

it('cenario 11: ActionGate middleware foi registrado como alias actiongate', function () {
    /** @var \Illuminate\Routing\Router $router */
    $router = app('router');
    $aliases = $router->getMiddleware();
    expect($aliases)->toHaveKey('actiongate');
    expect($aliases['actiongate'])->toBe(\Modules\Governance\Http\Middleware\ActionGate::class);
});
