<?php

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Brief — Daily Brief (ADR 0091).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Rotas Install (ADR 0024) foram registradas
 *   4. Rota API MCP brief-fetch foi registrada
 *
 * Refs: ADR 0091 Daily Brief, ADR 0024 receita criar módulo, skill criar-modulo
 */

it('cenario 1: modulo Brief aparece registrado em nWidart', function () {
    $module = Module::find('Brief');
    expect($module)->not->toBeNull('Modules/Brief deveria estar registrado');
    expect($module->getName())->toBe('Brief');
});

it('cenario 2: BriefServiceProvider esta carregado', function () {
    $module = Module::find('Brief');
    expect($module)->not->toBeNull();
    // Se o provider falhou no boot a app nem chega aqui — basta o módulo existir e estar enabled.
    expect($module->isStatus(1))->toBeTrue('Brief deveria estar enabled (status=1)');
});

it('cenario 3: rota api MCP brief-fetch existe nomeada', function () {
    expect(\Route::has('mcp.tools.brief-fetch'))
        ->toBeTrue('Rota mcp.tools.brief-fetch deveria existir per Routes/api.php');
});

it('cenario 4: rotas Install ADR 0024 estao registradas', function () {
    // Rotas /brief/install, /brief/install/uninstall, /brief/install/update
    // existem mas não são nomeadas — checamos via routes collection.
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($uri) => str_starts_with($uri, 'brief/install'));

    expect($routes->contains('brief/install'))
        ->toBeTrue('Rota brief/install (ADR 0024) deveria existir');
    expect($routes->contains('brief/install/uninstall'))
        ->toBeTrue('Rota brief/install/uninstall (ADR 0024) deveria existir');
    expect($routes->contains('brief/install/update'))
        ->toBeTrue('Rota brief/install/update (ADR 0024) deveria existir');
});

it('cenario 5: DataController expõe superadmin_package e user_permissions', function () {
    $controller = new \Modules\Brief\Http\Controllers\DataController;

    $pkg = $controller->superadmin_package();
    expect($pkg)->toBeArray()->and($pkg)->not->toBeEmpty();
    expect($pkg[0])->toHaveKey('name')
        ->and($pkg[0]['name'])->toBe('brief_module');

    $perms = $controller->user_permissions();
    expect($perms)->toBeArray()->and($perms)->not->toBeEmpty();
    expect($perms[0])->toHaveKey('value')
        ->and($perms[0]['value'])->toBe('brief.access');
});
