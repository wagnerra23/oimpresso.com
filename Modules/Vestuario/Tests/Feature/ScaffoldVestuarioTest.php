<?php

declare(strict_types=1);

use Modules\Vestuario\Http\Controllers\InstallController;
use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Vestuario (Sprint 1 ADR 0121 §P7).
 *
 * Garante que:
 *   1. Modulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas Install /vestuario foram registradas
 *   4. InstallController metadados corretos (moduleName/moduleSystemKey/version)
 *
 * Refs: ADR 0011 padrao Jana/Repair/Project, ADR 0024 BaseModuleInstallController,
 * ADR 0121 §P7 vertical vestuario, skill criar-modulo.
 *
 * Cliente piloto: ROTA LIVRE biz=4 (Larissa) — NUNCA usar biz=4 em testes (ADR 0101).
 */

it('cenario 1: modulo Vestuario aparece registrado em nWidart', function () {
    $module = Module::find('Vestuario');
    expect($module)->not->toBeNull('Modules/Vestuario deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Vestuario');
});

it('cenario 2: modulo Vestuario tem moduleName Vestuario via InstallController', function () {
    $controller = new InstallController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('moduleName');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('Vestuario');
});

it('cenario 3: moduleSystemKey é vestuario lowercase sem hifen', function () {
    $controller = new InstallController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('moduleSystemKey');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe('vestuario');
});

it('cenario 4: moduleVersion segue semver', function () {
    $controller = new InstallController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('moduleVersion');
    $method->setAccessible(true);

    $version = $method->invoke($controller);
    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
});

it('cenario 5: InstallController estende BaseModuleInstallController', function () {
    $parent = get_parent_class(InstallController::class);
    expect($parent)->toBe(\App\Http\Controllers\BaseModuleInstallController::class);
});

it('cenario 6: rota GET /vestuario/install existe no Route::getRoutes', function () {
    $routes = collect(\Route::getRoutes()->getRoutes());

    $installRoute = $routes->first(fn ($r) => $r->uri() === 'vestuario/install' && in_array('GET', $r->methods()));

    expect($installRoute)->not->toBeNull('Rota GET /vestuario/install deveria existir (Routes/web.php)');
});

it('cenario 7: rota GET /vestuario/install/uninstall existe', function () {
    $routes = collect(\Route::getRoutes()->getRoutes());

    $route = $routes->first(fn ($r) => $r->uri() === 'vestuario/install/uninstall' && in_array('GET', $r->methods()));

    expect($route)->not->toBeNull('Rota uninstall deveria existir');
});

it('cenario 8: rota GET /vestuario/install/update existe', function () {
    $routes = collect(\Route::getRoutes()->getRoutes());

    $route = $routes->first(fn ($r) => $r->uri() === 'vestuario/install/update' && in_array('GET', $r->methods()));

    expect($route)->not->toBeNull('Rota update deveria existir');
});

it('cenario 9: ServiceProvider Modules\\Vestuario\\Providers\\VestuarioServiceProvider existe', function () {
    expect(class_exists(\Modules\Vestuario\Providers\VestuarioServiceProvider::class))
        ->toBeTrue('VestuarioServiceProvider deveria existir per scaffold nWidart');
});
