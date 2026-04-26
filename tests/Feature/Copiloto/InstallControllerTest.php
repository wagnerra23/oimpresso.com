<?php

declare(strict_types=1);

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Route;
use Modules\Copiloto\Http\Controllers\InstallController;

uses(\Tests\TestCase::class);

/**
 * Garante que o módulo Copiloto integra com o /manage-modules:
 *
 * - InstallController existe e estende BaseModuleInstallController
 *   (sem isso, click em "Install" no /manage-modules dá 404)
 *
 * - Rotas /copiloto/install/* estão registradas
 *   (mesmo com o controller, sem rota não chega lá)
 *
 * - Module ativado em modules_statuses.json + module.json
 *   (sem isso, Module::allEnabled() ignora e o menu não monta)
 *
 * Testes não-HTTP (não precisam DB/seed) — rodam rápido.
 */

it('InstallController existe e estende BaseModuleInstallController', function () {
    expect(class_exists(InstallController::class))->toBeTrue();
    expect(is_subclass_of(InstallController::class, BaseModuleInstallController::class))->toBeTrue();
});

it('rota copiloto.install.index está registrada apontando pro InstallController', function () {
    $route = Route::getRoutes()->getByName('copiloto.install.index');

    expect($route)->not->toBeNull('rota copiloto.install.index ausente');
    expect($route->uri())->toBe('copiloto/install');
    expect($route->getActionName())
        ->toBe(InstallController::class.'@index');
});

it('rota copiloto.install.uninstall está registrada', function () {
    $route = Route::getRoutes()->getByName('copiloto.install.uninstall');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('copiloto/install/uninstall');
});

it('rota copiloto.install.update está registrada', function () {
    $route = Route::getRoutes()->getByName('copiloto.install.update');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('copiloto/install/update');
});

it('Copiloto está ativo em modules_statuses.json', function () {
    $statuses = json_decode(
        file_get_contents(base_path('modules_statuses.json')),
        true
    );

    expect($statuses)->toHaveKey('Copiloto')
        ->and($statuses['Copiloto'])->toBeTrue('Copiloto deve estar true em modules_statuses.json');
});

it('Modules/Copiloto/module.json tem active=1', function () {
    $manifest = json_decode(
        file_get_contents(base_path('Modules/Copiloto/module.json')),
        true
    );

    expect($manifest['active'])->toBe(1);
});

it('moduleSystemKey retorna copiloto (consistente com config)', function () {
    $controller = new InstallController;
    $reflection = new ReflectionMethod($controller, 'moduleSystemKey');
    $reflection->setAccessible(true);

    expect($reflection->invoke($controller))->toBe('copiloto');
});

it('moduleVersion lê config copiloto.module_version', function () {
    config(['copiloto.module_version' => '9.9']);

    $controller = new InstallController;
    $reflection = new ReflectionMethod($controller, 'moduleVersion');
    $reflection->setAccessible(true);

    expect($reflection->invoke($controller))->toBe('9.9');
});
