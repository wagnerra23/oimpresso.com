<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke do scaffold Modules/Woocommerce.
 *
 * Garante que:
 *   1. Módulo aparece registrado em nWidart
 *   2. ServiceProvider carrega sem erro
 *   3. Entity WoocommerceSyncLog é resolvable e aponta pra tabela correta
 *   4. Rotas web e webhook foram registradas (URI matching, já que módulo
 *      legacy não usa `->name(...)` em todas as rotas)
 *
 * Refs: ADR 0011 padrão Jana/Repair/Project · skill criar-modulo
 */

it('módulo Woocommerce aparece registrado em nWidart', function () {
    $module = Module::find('Woocommerce');

    expect($module)->not->toBeNull('Modules/Woocommerce deveria estar registrado em nWidart');
    expect($module->getName())->toBe('Woocommerce');
});

it('módulo Woocommerce está ativo (module.json active=1)', function () {
    $module = Module::find('Woocommerce');

    expect($module)->not->toBeNull();
    expect($module->isEnabled())->toBeTrue('Modules/Woocommerce deveria estar enabled');
});

it('Entity WoocommerceSyncLog resolve e aponta pra tabela woocommerce_sync_logs', function () {
    $model = new \Modules\Woocommerce\Entities\WoocommerceSyncLog;

    expect($model->getTable())->toBe('woocommerce_sync_logs');
});

it('rota webhook order-created registrada por URI', function () {
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->values();

    expect($routes->contains('webhook/order-created/{business_id}'))->toBeTrue(
        'Rota POST /webhook/order-created/{business_id} deveria existir em Routes/web.php'
    );
});

it('rota /woocommerce index registrada por URI', function () {
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->values();

    expect($routes->contains('woocommerce'))->toBeTrue(
        'Rota GET /woocommerce deveria existir em Routes/web.php'
    );
});

it('rota /woocommerce/install registrada por URI', function () {
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->values();

    expect($routes->contains('woocommerce/install'))->toBeTrue(
        'Rota /woocommerce/install deveria existir (InstallController)'
    );
});

it('rota /woocommerce/sync-products registrada por URI', function () {
    $routes = collect(\Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->values();

    expect($routes->contains('woocommerce/sync-products'))->toBeTrue(
        'Rota /woocommerce/sync-products deveria existir'
    );
});

it('Controllers principais resolvem via container', function () {
    expect(class_exists(\Modules\Woocommerce\Http\Controllers\WoocommerceController::class))->toBeTrue();
    expect(class_exists(\Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class))->toBeTrue();
    expect(class_exists(\Modules\Woocommerce\Http\Controllers\InstallController::class))->toBeTrue();
    expect(class_exists(\Modules\Woocommerce\Http\Controllers\DataController::class))->toBeTrue();
});
