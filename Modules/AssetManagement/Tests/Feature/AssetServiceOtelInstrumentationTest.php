<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 23 D9.a — AssetManagement OTel instrumentation contract test.
 *
 * Garante que os 3 Services do AssetManagement (Asset, Allocation, Maintenance)
 * usam OtelHelper canônico (App\Util\OtelHelper) — D9 spans más Services (3/7 → 6/7).
 *
 * Zero-cost OTel: spans são no-op se `otel.enabled=false` (default).
 * Esta suite roda local sem custo — apenas verifica instrumentação via leitura source.
 *
 * @see App/Util/OtelHelper.php
 * @see Modules/AssetManagement/Services/AssetService.php
 * @see Modules/AssetManagement/Services/AssetAllocationService.php
 * @see Modules/AssetManagement/Services/AssetMaintenanceService.php
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 */

it('AssetService usa OtelHelper canônico (App\Util\OtelHelper)', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->not->toContain('OpenTelemetry\\API\\Trace\\TracerProviderInterface');
});

it('AssetService instrumenta criar/atualizar/remover com spanBiz()', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetService.php'));

    foreach (['criar', 'atualizar', 'remover'] as $metodo) {
        expect($source)->toContain("public function {$metodo}");
    }

    foreach ([
        'assetmanagement.asset.criar',
        'assetmanagement.asset.atualizar',
        'assetmanagement.asset.remover',
    ] as $span) {
        expect($source)->toContain("OtelHelper::spanBiz('{$span}'");
    }
});

it('AssetAllocationService usa OtelHelper canônico', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetAllocationService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
});

it('AssetAllocationService instrumenta criar/atualizar/remover com spanBiz()', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetAllocationService.php'));

    foreach ([
        'assetmanagement.allocation.criar',
        'assetmanagement.allocation.atualizar',
        'assetmanagement.allocation.remover',
    ] as $span) {
        expect($source)->toContain("OtelHelper::spanBiz('{$span}'");
    }
});

it('AssetMaintenanceService usa OtelHelper canônico', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetMaintenanceService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
});

it('AssetMaintenanceService instrumenta criar/atualizar/remover com spanBiz()', function () {
    $source = file_get_contents(base_path('Modules/AssetManagement/Services/AssetMaintenanceService.php'));

    foreach ([
        'assetmanagement.maintenance.criar',
        'assetmanagement.maintenance.atualizar',
        'assetmanagement.maintenance.remover',
    ] as $span) {
        expect($source)->toContain("OtelHelper::spanBiz('{$span}'");
    }
});
