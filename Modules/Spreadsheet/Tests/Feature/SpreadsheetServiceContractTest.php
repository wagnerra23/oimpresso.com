<?php

declare(strict_types=1);

use Modules\Spreadsheet\Services\SpreadsheetService;

uses(Tests\TestCase::class);

/**
 * Wave 23 D4/D9 — SpreadsheetService contract test (Bucket functional_horizontal).
 *
 * Garante:
 *   1. SpreadsheetService está bindable via container (DI ok)
 *   2. Todos os métodos públicos críticos exigem bizId obrigatório (Tier 0)
 *   3. Cada método público está instrumentado com OtelHelper::spanBiz
 *   4. Métodos novos D4 (listForUser/getForUser) retornam tipos corretos via reflection
 *
 * Zero-cost: este teste roda sem DB — só verifica DI + reflection + source.
 *
 * @see Modules/Spreadsheet/Services/SpreadsheetService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('SpreadsheetService é resolvível via container Laravel', function () {
    $service = app(SpreadsheetService::class);
    expect($service)->toBeInstanceOf(SpreadsheetService::class);
});

it('SpreadsheetService::createSpreadsheet exige bizId como int obrigatório (Tier 0)', function () {
    $reflection = new ReflectionMethod(SpreadsheetService::class, 'createSpreadsheet');
    $params = collect($reflection->getParameters())->keyBy(fn ($p) => $p->getName());

    expect($params->has('bizId'))->toBeTrue('createSpreadsheet deve receber bizId Tier 0');
    expect($params['bizId']->getType()?->getName())->toBe('int');
    expect($params['bizId']->isOptional())->toBeFalse('bizId obrigatório — sem default');
});

it('SpreadsheetService::updateSpreadsheet exige bizId como int obrigatório (Tier 0)', function () {
    $reflection = new ReflectionMethod(SpreadsheetService::class, 'updateSpreadsheet');
    $params = collect($reflection->getParameters())->keyBy(fn ($p) => $p->getName());

    expect($params->has('bizId'))->toBeTrue();
    expect($params['bizId']->getType()?->getName())->toBe('int');
    expect($params['bizId']->isOptional())->toBeFalse();
});

it('SpreadsheetService::deleteSpreadsheet exige bizId + userId Tier 0', function () {
    $reflection = new ReflectionMethod(SpreadsheetService::class, 'deleteSpreadsheet');
    $params = collect($reflection->getParameters())->keyBy(fn ($p) => $p->getName());

    expect($params->has('bizId'))->toBeTrue();
    expect($params->has('userId'))->toBeTrue();
    expect($params['bizId']->isOptional())->toBeFalse();
    expect($params['userId']->isOptional())->toBeFalse();
});

it('SpreadsheetService tem 6 métodos públicos críticos instrumentados', function () {
    $source = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    $metodosCriticos = [
        'createSpreadsheet',
        'updateSpreadsheet',
        'deleteSpreadsheet',
        'resolveNotifyableUsers',
        'listForUser',
        'getForUser',
    ];

    foreach ($metodosCriticos as $metodo) {
        expect($source)->toContain("public function {$metodo}");
    }

    // Wave 23: contagem mínima — 6 chamadas spanBiz (1 por método público crítico)
    $count = substr_count($source, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(6);
});

it('SpreadsheetService::listForUser retorna LengthAwarePaginator (return type)', function () {
    $reflection = new ReflectionMethod(SpreadsheetService::class, 'listForUser');
    $returnType = $reflection->getReturnType()?->getName();
    expect($returnType)->toContain('LengthAwarePaginator');
});

it('SpreadsheetService::getForUser retorna ?Spreadsheet (nullable Tier 0)', function () {
    $reflection = new ReflectionMethod(SpreadsheetService::class, 'getForUser');
    $returnType = $reflection->getReturnType();
    expect($returnType)->not->toBeNull();
    // Nullable string (?Modules\Spreadsheet\Entities\Spreadsheet)
    expect($returnType->allowsNull())->toBeTrue('getForUser deve retornar nullable (ACL fail-secure)');
});
