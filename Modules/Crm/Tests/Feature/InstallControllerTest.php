<?php

declare(strict_types=1);

use App\Http\Controllers\BaseModuleInstallController;
use Modules\Crm\Http\Controllers\InstallController;

uses(Tests\TestCase::class);

/**
 * Smoke tests do InstallController — Modules/Crm.
 *
 * Valida que o controller está corretamente configurado para o install 1-click
 * (ADR 0024 / BaseModuleInstallController). Os métodos protegidos retornam os
 * valores esperados pelo framework de instalação.
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0024-instalacao-1-clique-modulos.md
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md §5
 */

it('InstallController estende BaseModuleInstallController', function () {
    $controller = new InstallController();
    expect($controller)->toBeInstanceOf(BaseModuleInstallController::class);
});

it('InstallController::moduleName() retorna Crm', function () {
    $controller = new InstallController();
    $method = new ReflectionMethod($controller, 'moduleName');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('Crm');
});

it('InstallController::moduleSystemKey() retorna crm (lowercase)', function () {
    // Convenção UltimatePOS: moduleSystemKey === strtolower(moduleName) — kebab quebra
    // isModuleInstalled() em app/Utils/ModuleUtil.php. Bug catalogado 2026-05-13.
    $controller = new InstallController();
    $method = new ReflectionMethod($controller, 'moduleSystemKey');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('crm');
});

it('InstallController::moduleVersion() retorna versão semver-like válida', function () {
    $controller = new InstallController();
    $method = new ReflectionMethod($controller, 'moduleVersion');
    $method->setAccessible(true);

    $version = $method->invoke($controller);
    expect($version)->toBeString();
    // CRM usa formato X.Y (config crm.module_version default '2.1') — aceita X.Y ou X.Y.Z
    expect($version)->toMatch('/^\d+\.\d+(\.\d+)?$/');
});

it('CrmServiceProvider registrado em module.json', function () {
    $manifest = json_decode(file_get_contents(
        __DIR__.'/../../module.json'
    ), true);

    expect($manifest['name'])->toBe('Crm');
    expect($manifest['alias'])->toBe('crm');
    expect($manifest['providers'])->toContain('Modules\\Crm\\Providers\\CrmServiceProvider');
});
