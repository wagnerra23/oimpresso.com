<?php

/**
 * Modules\Repair — sanity check do mapa de rotas.
 *
 * O grupo /repair é o "core" administrativo. Quebrar o registro
 * destas rotas é incidente regressivo conhecido (sessão 13).
 */

it('registra controllers principais do módulo', function () {
    $controllers = [
        \Modules\Repair\Http\Controllers\RepairController::class,
        \Modules\Repair\Http\Controllers\RepairStatusController::class,
        \Modules\Repair\Http\Controllers\JobSheetController::class,
        \Modules\Repair\Http\Controllers\DeviceModelController::class,
        \Modules\Repair\Http\Controllers\DashboardController::class,
        \Modules\Repair\Http\Controllers\InstallController::class,
        \Modules\Repair\Http\Controllers\CustomerRepairStatusController::class,
        \Modules\Repair\Http\Controllers\RepairSettingsController::class,
    ];

    foreach ($controllers as $class) {
        expect(class_exists($class))->toBeTrue("controller ausente: {$class}");
    }
});

it('garante stack de middleware UltimatePOS no grupo /repair', function () {
    $middleware = routeMiddleware('repair/repair');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('SetSessionData')
        ->and($middleware)->toContain('AdminSidebarMenu');
});

it('mapeia o resource controller principal', function () {
    expect(routeExists('repair/repair', 'GET'))->toBeTrue()
        ->and(routeExists('repair/repair', 'POST'))->toBeTrue();
});
