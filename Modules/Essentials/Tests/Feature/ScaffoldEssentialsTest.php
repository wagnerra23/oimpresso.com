<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Scaffold guard — garante que o módulo Essentials está corretamente
 * registrado no scanner do nWidart e expõe rotas básicas.
 *
 * Detecta regressão tipo: alguém move pasta, renomeia `module.json` ou quebra
 * autoload PSR-4 → módulo some silenciosamente do `Module::find('Essentials')`.
 *
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md (8 peças obrigatórias)
 */

it('módulo Essentials está registrado no nWidart', function () {
    $module = Module::find('Essentials');

    expect($module)->not->toBeNull();
    expect($module->getName())->toBe('Essentials');
});

it('módulo Essentials está ativo (status enabled)', function () {
    $module = Module::find('Essentials');

    expect($module)->not->toBeNull();
    expect($module->isEnabled())->toBeTrue();
});

it('módulo Essentials expõe rota /essentials/ index', function () {
    $found = false;
    foreach (Route::getRoutes() as $route) {
        if ($route->uri() === 'essentials' && in_array('GET', $route->methods(), true)) {
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

it('módulo Essentials expõe pelo menos 1 rota com prefix essentials/', function () {
    $count = 0;
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'essentials/')) {
            $count++;
        }
    }

    // Módulo grande — esperamos dezenas. Mínimo razoável: 10.
    expect($count)->toBeGreaterThanOrEqual(10);
});

it('module.json existe com chaves canon', function () {
    $module = Module::find('Essentials');

    expect($module)->not->toBeNull();

    $path = $module->getPath() . DIRECTORY_SEPARATOR . 'module.json';
    expect(file_exists($path))->toBeTrue();

    $json = json_decode(file_get_contents($path), true);
    expect($json)->toBeArray();
    expect($json)->toHaveKeys(['name', 'alias', 'providers']);
    expect($json['name'])->toBe('Essentials');
});

it('ServiceProvider do módulo Essentials está autoloaded', function () {
    $providerClass = \Modules\Essentials\Providers\EssentialsServiceProvider::class;
    expect(class_exists($providerClass))->toBeTrue();
});
