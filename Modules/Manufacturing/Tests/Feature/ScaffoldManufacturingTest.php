<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/Manufacturing.
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart (module.json carregado)
 *   2. ServiceProvider ManufacturingServiceProvider carregou sem erro
 *   3. Rotas resource principais (recipe + production + settings) registradas
 *   4. Service novo (RecipeBomService) instanciável via container
 *
 * Refs: module.json (provider ManufacturingServiceProvider), ADR 0011 padrão Jana/Repair/Project.
 *       D4.a ratio Service/Controller — RecipeBomService extraído do RecipeController.
 *
 * @see memory/decisions/0011-alinhamento-padrao-jana.md
 * @see Modules/Manufacturing/Services/RecipeBomService.php
 */

it('cenario 1: modulo Manufacturing aparece registrado em nWidart', function () {
    $module = Module::find('Manufacturing');
    expect($module)->not->toBeNull('Modules/Manufacturing deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Manufacturing');
});

it('cenario 2: modulo Manufacturing esta ativo (module.json active=1)', function () {
    $module = Module::find('Manufacturing');
    expect($module)->not->toBeNull();
    expect($module->isEnabled())->toBeTrue('Manufacturing deveria estar habilitado per module.json');
});

it('cenario 3: rotas resource principais registradas (recipe/production/settings index)', function () {
    expect(\Route::has('recipe.index'))->toBeTrue('recipe.index deveria existir per Route::resource em Routes/web.php');
    expect(\Route::has('production.index'))->toBeTrue('production.index deveria existir per Route::resource em Routes/web.php');
    expect(\Route::has('settings.index'))->toBeTrue('settings.index deveria existir per Route::resource em Routes/web.php');
});

it('cenario 4: RecipeBomService instanciavel via container Laravel (DI funciona)', function () {
    $service = app(\Modules\Manufacturing\Services\RecipeBomService::class);
    expect($service)->toBeInstanceOf(\Modules\Manufacturing\Services\RecipeBomService::class);
});

it('cenario 5: RecipeBomService::resolveBom retorna collection vazia pra recipe inexistente', function () {
    $service = app(\Modules\Manufacturing\Services\RecipeBomService::class);

    // Recipe ID inexistente + biz=1 (Wagner, ADR 0101 — nunca biz=cliente real)
    $bom = $service->resolveBom(recipeId: 99999999, businessId: 1);

    expect($bom)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($bom)->toBeEmpty();
});

it('cenario 6: ManufacturingServiceProvider registrado (config publishable)', function () {
    expect(config('manufacturing'))->toBeArray();
});
