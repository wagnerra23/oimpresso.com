<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Gap 3 (Sells r4 KB-9.75 cowork-bundle 2026-05-26) — printInvoice action.
 *
 * Foco: 3 specs MVP cobrindo AJAX-only + multi-tenant + happy path.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController::printInvoice
 * @see resources/views/oficina_auto/print/service_order.blade.php
 */

const BIZ_PRINT = 1;
const BIZ_PRINT_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing');
    }
});

function criarOsParaPrint(int $biz): ServiceOrder
{
    $v = Vehicle::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'plate' => 'ABC' . random_int(1000, 9999),
        'type' => 'caminhao_basculante',
        'brand' => 'Volvo',
        'model' => 'FH 540',
        'year' => 2020,
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id' => $v->id,
        'status' => 'aberta',
        'started_at' => now(),
        'mileage_at_service' => 245890,
        'notes' => 'Pastilhas freio dianteiro + disco par',
    ]);
}

function userPrintBiz(int $biz): User
{
    return User::withoutGlobalScopes()->firstOrCreate(
        ['email' => "print-test-biz-{$biz}@oimpresso.test"],
        [
            'business_id' => $biz,
            'first_name' => 'Test Print',
            'username' => "print_test_biz_{$biz}",
            'password' => bcrypt('password'),
            'language' => 'pt_BR',
        ],
    );
}

it('retorna JSON receipt quando AJAX request autenticado', function () {
    $os = criarOsParaPrint(BIZ_PRINT);
    $u = userPrintBiz(BIZ_PRINT);

    $response = $this->actingAs($u)
        ->get(
            route('oficinaauto.orders.print', $os->id),
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'receipt' => ['html_content', 'print_title'],
        ])
        ->assertJsonPath('success', true);
})->group('oficinaauto', 'print', 'gap3');

it('retorna 404 cross-tenant (business_id diferente)', function () {
    $os = criarOsParaPrint(BIZ_PRINT);
    $u = userPrintBiz(BIZ_PRINT_OUTRO);

    $response = $this->actingAs($u)
        ->get(
            route('oficinaauto.orders.print', $os->id),
            ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        );

    // Tier 0 ADR 0093: global scope filtra → RMB retorna 404 ModelNotFound
    $response->assertNotFound();
})->group('oficinaauto', 'print', 'multi-tenant', 'gap3');

it('retorna 401/redirect sem auth', function () {
    $os = criarOsParaPrint(BIZ_PRINT);

    $response = $this->get(
        route('oficinaauto.orders.print', $os->id),
        ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
    );

    // Middleware auth: unauthenticated → redirect login (302) ou 401 (json)
    expect($response->status())->toBeIn([302, 401]);
})->group('oficinaauto', 'print', 'auth', 'gap3');
