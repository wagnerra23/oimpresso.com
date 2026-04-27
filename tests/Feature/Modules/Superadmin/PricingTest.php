<?php

/**
 * Modules\Superadmin — página pública /pricing.
 *
 * Pricing redesenhada (vide handoff sessões 0023-0024) — view
 * superadmin::pricing.index renderizada sem auth pra captação.
 */

use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Entities\Package;

beforeEach(function () {
    if (!Schema::hasTable('packages')) {
        $this->markTestSkipped('Migrações Superadmin não rodadas no ambiente de teste.');
    }
});

it('expõe /pricing publicamente sem stack web/auth', function () {
    $route = moduleRoute('pricing');

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->not->toContain('auth')
        ->and($middleware)->not->toContain('superadmin');
});

it('renderiza a view de pricing redesenhada', function () {
    Package::create([
        'name' => 'Plano Pro',
        'description' => 'Plano premium',
        'location_count' => 1,
        'user_count' => 5,
        'product_count' => 100,
        'invoice_count' => 100,
        'interval' => 'months',
        'interval_count' => 1,
        'price' => 99.90,
        'is_active' => 1,
        'is_private' => 0,
        'sort_order' => 1,
    ]);

    $response = $this->get('/pricing');

    $response->assertOk();
    $response->assertViewIs('superadmin::pricing.index');
    $response->assertViewHas('packages');
    $response->assertViewHas('permission_formatted');
});

it('omite pacotes inativos da listagem pública', function () {
    Package::create([
        'name' => 'Plano Oculto', 'description' => '', 'location_count' => 1,
        'user_count' => 1, 'product_count' => 1, 'invoice_count' => 1,
        'interval' => 'months', 'interval_count' => 1, 'price' => 1,
        'is_active' => 0, 'is_private' => 0, 'sort_order' => 99,
    ]);

    $response = $this->get('/pricing');
    $response->assertOk();
    $response->assertDontSee('Plano Oculto');
});
