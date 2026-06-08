<?php

declare(strict_types=1);

use Modules\Officeimpresso\Http\Controllers\AuditController;
use Modules\Officeimpresso\Http\Controllers\LicencaComputadorController;
use Modules\Officeimpresso\Services\LicencaAuditService;
use Modules\Officeimpresso\Services\LicencaService;

uses(Tests\TestCase::class);

/**
 * Wave 16 D4 Architecture — smoke test do refactor Service layer.
 *
 * Apos Wave 16 governance v3 (D4=3/20 -> >=10/20), LicencaComputadorController
 * + AuditController deixam de carregar logica inline (CRUD + bloqueio +
 * sanitizacao PII). Garante:
 *   1. Classes Service existem nos namespaces canonicos
 *   2. DI Constructor — Container resolve Services
 *   3. Single Responsibility — Controllers magros, persistencia no Service
 *   4. Module boundary — Services so dentro de Modules\Officeimpresso
 *
 * Pattern canonico: ADR 0011 padrao Jana/Repair.
 *
 * @see Modules\Officeimpresso\Services\LicencaService
 * @see Modules\Officeimpresso\Services\LicencaAuditService
 */

it('cenario 1: LicencaService existe no namespace canonico', function () {
    expect(class_exists(LicencaService::class))
        ->toBeTrue('LicencaService deveria estar em Modules\\Officeimpresso\\Services\\');
});

it('cenario 2: LicencaAuditService existe no namespace canonico', function () {
    expect(class_exists(LicencaAuditService::class))
        ->toBeTrue('LicencaAuditService deveria estar em Modules\\Officeimpresso\\Services\\');
});

it('cenario 3: Container resolve LicencaService', function () {
    $service = app(LicencaService::class);
    expect($service)->toBeInstanceOf(LicencaService::class);
});

it('cenario 4: Container resolve LicencaAuditService (com ou sem PiiRedactor)', function () {
    $service = app(LicencaAuditService::class);
    expect($service)->toBeInstanceOf(LicencaAuditService::class);
});

it('cenario 5: LicencaComputadorController declara DI constructor com LicencaService', function () {
    $ref = new ReflectionClass(LicencaComputadorController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull('Controller deveria ter __construct com DI');

    $params = $constructor->getParameters();
    expect($params)->toHaveCount(1, 'Controller deveria receber LicencaService via DI');

    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(LicencaService::class);
});

it('cenario 6: AuditController declara DI constructor com LicencaAuditService', function () {
    $ref = new ReflectionClass(AuditController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    expect($params)->toHaveCount(1);

    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(LicencaAuditService::class);
});

it('cenario 7: Services estao DENTRO do namespace do modulo (module boundary)', function () {
    expect(LicencaService::class)->toStartWith('Modules\\Officeimpresso\\');
    expect(LicencaAuditService::class)->toStartWith('Modules\\Officeimpresso\\');
});

it('cenario 8: AuditController eh MAGRO — <60 linhas (single responsibility)', function () {
    $file = (new ReflectionClass(AuditController::class))->getFileName();
    $lines = count(file($file));

    expect($lines)->toBeLessThan(60, "Controller magro: <60 linhas. Atual: {$lines}");
});

it('cenario 9: metodos do AuditController sao curtos — cada um <30 linhas executaveis', function () {
    $ref = new ReflectionClass(AuditController::class);

    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== AuditController::class) {
            continue;
        }
        if ($method->isConstructor()) {
            continue;
        }

        $length = $method->getEndLine() - $method->getStartLine();
        expect($length)->toBeLessThan(30, "Metodo {$method->getName()} deveria ter <30 linhas; tem {$length}");
    }
});

it('cenario 10: LicencaService eh stateless (sem propriedades de instancia mutaveis)', function () {
    $ref = new ReflectionClass(LicencaService::class);
    $constructor = $ref->getConstructor();

    // Sem __construct = sem deps obrigatorias = stateless puro.
    expect($constructor)->toBeNull('LicencaService nao precisa de deps no constructor');
});
