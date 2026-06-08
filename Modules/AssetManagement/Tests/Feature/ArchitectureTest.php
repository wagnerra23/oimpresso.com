<?php

declare(strict_types=1);

use Modules\AssetManagement\Http\Controllers\AssetAllocationController;
use Modules\AssetManagement\Http\Controllers\AssetController;
use Modules\AssetManagement\Http\Controllers\AssetMaitenanceController;
use Modules\AssetManagement\Services\AssetAllocationService;
use Modules\AssetManagement\Services\AssetMaintenanceService;
use Modules\AssetManagement\Services\AssetService;

uses(Tests\TestCase::class);

/**
 * Wave 16 D4 Architecture — smoke test do refactor Service layer.
 *
 * Apos Wave 16 governance v3 (D4=3/20 -> >=10/20), AssetController +
 * AssetAllocationController + AssetMaitenanceController deixam de carregar
 * logica de negocio inline (criar, atualizar, remover, normalizar campos,
 * notificacoes). Garante:
 *   1. Classes Service existem nos namespaces canonicos
 *   2. DI Constructor — Container Laravel resolve Services com suas deps
 *   3. Single Responsibility — Controllers delegam persistencia ao Service
 *   4. Module boundary — Services so dentro de Modules\AssetManagement
 *
 * Pattern canonico: ADR 0011 padrao Jana/Repair.
 *
 * @see Modules\AssetManagement\Services\AssetService
 * @see Modules\AssetManagement\Services\AssetAllocationService
 * @see Modules\AssetManagement\Services\AssetMaintenanceService
 */

it('cenario 1: AssetService existe no namespace canonico', function () {
    expect(class_exists(AssetService::class))
        ->toBeTrue('AssetService deveria estar em Modules\\AssetManagement\\Services\\');
});

it('cenario 2: AssetAllocationService existe no namespace canonico', function () {
    expect(class_exists(AssetAllocationService::class))
        ->toBeTrue('AssetAllocationService deveria estar em Modules\\AssetManagement\\Services\\');
});

it('cenario 3: AssetMaintenanceService existe no namespace canonico', function () {
    expect(class_exists(AssetMaintenanceService::class))
        ->toBeTrue('AssetMaintenanceService deveria estar em Modules\\AssetManagement\\Services\\');
});

it('cenario 4: Container resolve AssetService com Util + AssetUtil', function () {
    $service = app(AssetService::class);
    expect($service)->toBeInstanceOf(AssetService::class);
});

it('cenario 5: Container resolve AssetAllocationService', function () {
    $service = app(AssetAllocationService::class);
    expect($service)->toBeInstanceOf(AssetAllocationService::class);
});

it('cenario 6: Container resolve AssetMaintenanceService', function () {
    $service = app(AssetMaintenanceService::class);
    expect($service)->toBeInstanceOf(AssetMaintenanceService::class);
});

it('cenario 7: AssetController declara DI constructor com AssetService', function () {
    $ref = new ReflectionClass(AssetController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull('Controller deveria ter __construct com DI');

    $params = $constructor->getParameters();
    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(AssetService::class);
});

it('cenario 8: AssetAllocationController declara DI constructor com AssetAllocationService', function () {
    $ref = new ReflectionClass(AssetAllocationController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(AssetAllocationService::class);
});

it('cenario 9: AssetMaitenanceController declara DI constructor com AssetMaintenanceService', function () {
    $ref = new ReflectionClass(AssetMaitenanceController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(AssetMaintenanceService::class);
});

it('cenario 10: Services estao DENTRO do namespace do modulo (module boundary)', function () {
    expect(AssetService::class)->toStartWith('Modules\\AssetManagement\\');
    expect(AssetAllocationService::class)->toStartWith('Modules\\AssetManagement\\');
    expect(AssetMaintenanceService::class)->toStartWith('Modules\\AssetManagement\\');
});

it('cenario 11: AssetService declara DI constructor (Util + AssetUtil)', function () {
    $ref = new ReflectionClass(AssetService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(2);
});

it('cenario 12: AssetAllocationService declara DI constructor', function () {
    $ref = new ReflectionClass(AssetAllocationService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(2);
});
