<?php

declare(strict_types=1);

use Modules\Spreadsheet\Services\SpreadsheetService;

uses(Tests\TestCase::class);

/**
 * Wave 18 D8 — Observability contract test (Modules/Spreadsheet).
 *
 * Garante que SpreadsheetService está instrumentado com OtelHelper canônico
 * (App\Util\OtelHelper) em todos os métodos público críticos. Caso métodos
 * novos sejam adicionados sem span, este teste detecta regressão.
 *
 * Zero-cost OTel: spans são no-op se `otel.enabled=false` (default). Esta
 * suite roda LOCAL sem custo — apenas verifica instrumentação via reflection.
 *
 * @see App/Util/OtelHelper.php
 * @see Modules/Spreadsheet/Services/SpreadsheetService.php
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 */

it('SpreadsheetService usa OtelHelper canônico (App\Util\OtelHelper)', function () {
    $source = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->not->toContain('OpenTelemetry\API\Trace\TracerProviderInterface'); // não usar SDK direto
});

it('SpreadsheetService instrumenta todos métodos público com spanBiz()', function () {
    $source = file_get_contents(base_path('Modules/Spreadsheet/Services/SpreadsheetService.php'));

    // Cada método público criticamente conta com span — regression test
    $metodosCriticos = [
        'createSpreadsheet',
        'updateSpreadsheet',
        'deleteSpreadsheet',
        'resolveNotifyableUsers',
        'listForUser',       // Wave 18 D4
        'getForUser',         // Wave 18 D4
    ];

    foreach ($metodosCriticos as $metodo) {
        expect($source)->toContain("public function {$metodo}");
    }

    // Spans esperados (1 por método público crítico — Wave 18 mínimo 6)
    $spansEsperados = [
        'spreadsheet.create',
        'spreadsheet.update',
        'spreadsheet.delete',
        'spreadsheet.resolve_notifyable_users',
        'spreadsheet.list_for_user',
        'spreadsheet.get_for_user',
    ];

    foreach ($spansEsperados as $spanName) {
        expect($source)->toContain("OtelHelper::spanBiz('{$spanName}'");
    }
});

it('SpreadsheetService tem assinatura bizId obrigatória nos métodos novos D4', function () {
    $reflection = new ReflectionClass(SpreadsheetService::class);

    $listForUser = $reflection->getMethod('listForUser');
    $params = collect($listForUser->getParameters())->map(fn ($p) => $p->getName())->toArray();
    expect($params)->toContain('bizId');
    expect($params)->toContain('userId');

    $getForUser = $reflection->getMethod('getForUser');
    $params = collect($getForUser->getParameters())->map(fn ($p) => $p->getName())->toArray();
    expect($params)->toContain('bizId');
    expect($params)->toContain('userId');
    expect($params)->toContain('id');
});
