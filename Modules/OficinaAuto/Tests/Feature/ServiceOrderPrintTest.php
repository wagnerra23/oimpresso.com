<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Gap 3 US-OFICINA-037 — Pest specs pra ServiceOrderController::printInvoice
 * (papel A4 nota-fiscal-like que substitui window.print bare).
 *
 * 4 specs:
 *   1. AJAX request retorna JSON {success:1, receipt:{html_content, print_title}}
 *   2. Sem X-Requested-With (request não-AJAX) retorna 404
 *   3. Cross-tenant (user biz=A tentando OS biz=B) retorna 404
 *   4. HTML content inclui número OS + total dos items + zone labels
 *
 * Multi-tenant Tier 0 (ADR 0093) — defensive guard explícito no Controller
 * + Route Model Binding global scope.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php@printInvoice
 * @see resources/views/oficina_auto/print/service_order.blade.php
 * @see memory/sessions/2026-05-26-plano-gap-3-imprimir-os-pdf-profissional.md
 */

const PRINT_BIZ_A = 1;
const PRINT_BIZ_B = 2;
const PRINT_PLATE_PREFIX = 'GAP3P';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('Migration service_orders ausente');
    }
});

function gap3_criaOsComItens(string $suffix, int $business, int $itemsCount = 2): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $business,
        'plate'        => PRINT_PLATE_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $business,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'notes'       => 'OS de teste Gap 3 print',
    ]);

    if (Schema::hasTable('oficina_service_order_items')) {
        for ($i = 1; $i <= $itemsCount; $i++) {
            ServiceOrderItem::withoutGlobalScopes()->create([
                'business_id'      => $business,
                'service_order_id' => $os->id,
                'tipo'             => ServiceOrderItem::TIPO_PECA,
                'descricao'        => "Peca Gap3 #{$i}",
                'quantidade'       => 1,
                'valor_unitario'   => 100.00 * $i,
            ]);
        }
    }

    return $os;
}

function gap3_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PRINT_PLATE_PREFIX . $suffix . '%')
        ->pluck('id')
        ->toArray();

    if (empty($vehicles)) {
        return;
    }

    $osIds = ServiceOrder::withoutGlobalScopes()
        ->whereIn('vehicle_id', $vehicles)
        ->pluck('id')
        ->toArray();

    if (! empty($osIds) && Schema::hasTable('oficina_service_order_items')) {
        ServiceOrderItem::withoutGlobalScopes()
            ->whereIn('service_order_id', $osIds)
            ->forceDelete();
    }
    if (! empty($osIds)) {
        ServiceOrder::withoutGlobalScopes()
            ->whereIn('id', $osIds)
            ->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// Spec 1 — AJAX request retorna JSON com receipt
// ---------------------------------------------------------------------------

it('returns receipt JSON when AJAX request', function () {
    session(['user.business_id' => PRINT_BIZ_A]);
    $os = gap3_criaOsComItens('A1', PRINT_BIZ_A, itemsCount: 2);

    $user = User::factory()->create(['business_id' => PRINT_BIZ_A]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])
        ->get("/oficina-auto/ordens-servico/{$os->id}/print");

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'receipt' => [
            'html_content',
            'print_title',
        ],
    ]);
    expect($response->json('success'))->toBe(1);
    expect($response->json('receipt.html_content'))->toBeString()->not->toBeEmpty();
    expect($response->json('receipt.print_title'))->toContain('OS-');
})->afterEach(fn () => gap3_cleanup('A1'));

// ---------------------------------------------------------------------------
// Spec 2 — Sem X-Requested-With (non-AJAX) retorna 404
// ---------------------------------------------------------------------------

it('returns 404 without AJAX header', function () {
    session(['user.business_id' => PRINT_BIZ_A]);
    $os = gap3_criaOsComItens('A2', PRINT_BIZ_A, itemsCount: 1);

    $user = User::factory()->create(['business_id' => PRINT_BIZ_A]);
    $user->givePermissionTo('superadmin');

    // Sem X-Requested-With: Controller aborta 404 (evita AppShellV2 vazado).
    $response = $this->actingAs($user)
        ->get("/oficina-auto/ordens-servico/{$os->id}/print");

    $response->assertNotFound();
})->afterEach(fn () => gap3_cleanup('A2'));

// ---------------------------------------------------------------------------
// Spec 3 — Cross-tenant guard (ADR 0093 Tier 0)
// ---------------------------------------------------------------------------

it('returns 404 cross-tenant', function () {
    // OS criada em biz=B ...
    $osBizB = gap3_criaOsComItens('B1', PRINT_BIZ_B, itemsCount: 1);

    // ... user logado em biz=A tenta acessar.
    session(['user.business_id' => PRINT_BIZ_A]);
    $user = User::factory()->create(['business_id' => PRINT_BIZ_A]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])
        ->get("/oficina-auto/ordens-servico/{$osBizB->id}/print");

    // Route Model Binding já filtra via global scope = 404 antes mesmo do controller.
    // Defensive guard explícito reforça no controller.
    $response->assertNotFound();
})->afterEach(fn () => gap3_cleanup('B1'));

// ---------------------------------------------------------------------------
// Spec 4 — HTML content inclui número OS + valor total + zone labels
// ---------------------------------------------------------------------------

it('includes correct total in receipt HTML', function () {
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('oficina_service_order_items ausente — pula validacao items');
    }

    session(['user.business_id' => PRINT_BIZ_A]);
    // 2 items: R$ 100 + R$ 200 = R$ 300 total
    $os = gap3_criaOsComItens('A3', PRINT_BIZ_A, itemsCount: 2);

    $user = User::factory()->create(['business_id' => PRINT_BIZ_A]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])
        ->get("/oficina-auto/ordens-servico/{$os->id}/print");

    $response->assertOk();
    $html = $response->json('receipt.html_content');

    // Numero OS deve aparecer (formato OS-NNNNN).
    expect($html)->toContain('OS-' . str_pad((string) $os->id, 5, '0', STR_PAD_LEFT));

    // Total R$ 300,00 (item1 R$ 100 + item2 R$ 200) — formato pt-BR.
    expect($html)->toMatch('/R\$\s*300[,.]00/');

    // Headers das zones aparecem (zone labels do Blade).
    expect($html)->toContain('Cliente');
    expect($html)->toContain('Veículo');
    expect($html)->toContain('Itens da OS');
    expect($html)->toContain('TOTAL');
})->afterEach(fn () => gap3_cleanup('A3'));
