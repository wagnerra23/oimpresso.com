<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Smoke D4 architecture — Wave 16 governance v3.
 *
 * Garante que:
 *   1. Service layer SpreadsheetService existe (extraído do Controller 567 linhas)
 *   2. Service expõe métodos canônicos (createSpreadsheet/updateSpreadsheet/deleteSpreadsheet)
 *   3. Controller depende do Service via DI (não instancia direto)
 *   4. Service pode ser resolvido via container Laravel
 *
 * Refs: ADR 0155 module-grade-v3 D4 architecture (Service layer separation of concerns)
 */

it('cenário 1: classe SpreadsheetService existe no namespace canônico', function () {
    expect(class_exists(\Modules\Spreadsheet\Services\SpreadsheetService::class))
        ->toBeTrue('Service layer extraído do Controller deve existir');
});

it('cenário 2: SpreadsheetService expõe métodos canônicos create/update/delete', function () {
    $svc = \Modules\Spreadsheet\Services\SpreadsheetService::class;
    expect(method_exists($svc, 'createSpreadsheet'))->toBeTrue();
    expect(method_exists($svc, 'updateSpreadsheet'))->toBeTrue();
    expect(method_exists($svc, 'deleteSpreadsheet'))->toBeTrue();
});

it('cenário 3: SpreadsheetController depende de SpreadsheetService via construtor (DI)', function () {
    $reflection = new \ReflectionClass(\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class);
    $constructor = $reflection->getConstructor();
    expect($constructor)->not->toBeNull();

    $params = $constructor->getParameters();
    $paramTypes = array_map(
        fn ($p) => $p->getType() ? $p->getType()->getName() : null,
        $params
    );

    expect($paramTypes)->toContain(\Modules\Spreadsheet\Services\SpreadsheetService::class);
});

it('cenário 4: SpreadsheetService pode ser resolvido via container Laravel', function () {
    $svc = app(\Modules\Spreadsheet\Services\SpreadsheetService::class);
    expect($svc)->toBeInstanceOf(\Modules\Spreadsheet\Services\SpreadsheetService::class);
});
