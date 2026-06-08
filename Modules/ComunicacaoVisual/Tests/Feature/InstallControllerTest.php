<?php

declare(strict_types=1);

use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use App\Http\Controllers\BaseModuleInstallController;

uses(Tests\TestCase::class);

/**
 * Smoke tests do InstallController — ComunicacaoVisual.
 *
 * Valida que o controller está corretamente configurado para o install 1-click
 * (ADR 0024 / BaseModuleInstallController). Os métodos retornam os valores
 * esperados pelo framework de instalação.
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

it('InstallController::moduleName() retorna ComunicacaoVisual', function () {
    $controller = new InstallController();
    $method     = new ReflectionMethod($controller, 'moduleName');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('ComunicacaoVisual');
});

it('InstallController::moduleSystemKey() retorna comunicacaovisual (lowercase sem hífen)', function () {
    // Convenção UltimatePOS: moduleSystemKey === strtolower(moduleName) — kebab quebra
    // isModuleInstalled() em app/Utils/ModuleUtil.php:31. Bug catalogado 2026-05-13.
    $controller = new InstallController();
    $method     = new ReflectionMethod($controller, 'moduleSystemKey');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('comunicacaovisual');
});

it('InstallController::moduleVersion() retorna versão semver válida', function () {
    $controller = new InstallController();
    $method     = new ReflectionMethod($controller, 'moduleVersion');
    $method->setAccessible(true);

    $version = $method->invoke($controller);
    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
});

it('InstallController::successMessage() retorna string não vazia em PT-BR', function () {
    $controller = new InstallController();
    $method     = new ReflectionMethod($controller, 'successMessage');
    $method->setAccessible(true);

    $msg = $method->invoke($controller);
    expect($msg)->toBeString();
    expect(strlen($msg))->toBeGreaterThan(10);
});
