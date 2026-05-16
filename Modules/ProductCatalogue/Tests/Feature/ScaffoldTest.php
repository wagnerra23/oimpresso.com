<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke test do scaffold Modules/ProductCatalogue (Wave B — gap P3).
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart (laravel-modules ^10)
 *   2. ProductCatalogueServiceProvider carrega sem erro
 *   3. RouteServiceProvider registrou rotas web (URIs)
 *   4. InstallController estende BaseModuleInstallController (padrão UltimatePOS)
 *
 * NOTA — multi-tenant: ProductCatalogue é catálogo público (sem Eloquent Models/Entities
 * próprios — usa App\Product, App\Business, App\Discount herdados do core UltimatePOS).
 * Portanto NÃO há `MultiTenantIsolationTest` pra este módulo — isolamento é
 * coberto pelos testes dos models core (App\Product já tem business_id global scope).
 * Controllers recebem $business_id como parâmetro de rota pública (catálogo
 * compartilhável via QR code), respeitando filtro `Product::where('business_id', $bid)`.
 *
 * Refs: ADR 0011 padrão Jana/Repair, skill criar-modulo, [ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
 */

it('cenario 1: modulo ProductCatalogue aparece registrado em nWidart', function () {
    $module = Module::find('ProductCatalogue');
    expect($module)->not->toBeNull('Modules/ProductCatalogue deveria estar registrado em nWidart');
    expect($module->getName())->toBe('ProductCatalogue');
});

it('cenario 2: ServiceProvider do modulo esta carregado', function () {
    $providers = app()->getLoadedProviders();
    expect($providers)->toHaveKey(\Modules\ProductCatalogue\Providers\ProductCatalogueServiceProvider::class);
});

it('cenario 3: rota publica do catalogo (catalogue/{business}/{location}) esta registrada', function () {
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->toContain('catalogue/{business_id}/{location_id}');
});

it('cenario 4: rota show-catalogue/{business}/{product} esta registrada', function () {
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->toContain('show-catalogue/{business_id}/{product_id}');
});

it('cenario 5: rota admin product-catalogue/catalogue-qr esta registrada', function () {
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->toContain('product-catalogue/catalogue-qr');
});

it('cenario 6: InstallController estende BaseModuleInstallController (padrao UltimatePOS)', function () {
    expect(is_subclass_of(
        \Modules\ProductCatalogue\Http\Controllers\InstallController::class,
        \App\Http\Controllers\BaseModuleInstallController::class
    ))->toBeTrue('InstallController deveria estender BaseModuleInstallController per ADR 0011');
});

it('cenario 7: rotas de install product-catalogue (index/install/uninstall/update) registradas', function () {
    $routes = \Route::getRoutes();
    $uris = collect($routes)->map(fn ($r) => $r->uri())->all();

    expect($uris)->toContain('product-catalogue/install');
    expect($uris)->toContain('product-catalogue/install/uninstall');
    expect($uris)->toContain('product-catalogue/install/update');
});
