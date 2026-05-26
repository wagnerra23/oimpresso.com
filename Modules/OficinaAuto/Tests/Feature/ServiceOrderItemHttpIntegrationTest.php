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
 * Wave 5 US-OFICINA-005-bis — Pest integration tests pra UI items.
 *
 * Cobertura espelha pattern Wave 1.3 (ServiceOrderObserverItemsAndHttpTest)
 * mas focada no payload Inertia consumido por Show.tsx + Edit.tsx:
 *
 *   - GET /oficina-auto/ordens-servico/{id} (Inertia branch) inclui `items[]` no payload
 *   - GET /oficina-auto/ordens-servico/{id}/edit (Inertia branch) inclui `items[]` no payload
 *   - DELETE /oficina-auto/ordens-servico/{order}/items/{item} autenticado retorna 200 + deleted=true
 *   - DELETE cross-OS guard (item de OS1 via URL OS2 → 404 abort_unless)
 *
 * Multi-tenant Tier 0 [ADR 0093]: validado por testes Wave 1.3 já mergeados —
 * aqui só validamos shape do Inertia payload + happy path DELETE.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (show/edit Inertia)
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderItemController.php (destroy)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx (seção Itens da OS)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx (seção inline)
 */

const BIZ_WAVE5 = 1;
const PLATE_WAVE5_PREFIX = 'W5UI';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010 primeiro');
    }
});

function wave5_criaOsComItens(string $suffix, int $itemsCount = 2): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => BIZ_WAVE5,
        'plate'        => PLATE_WAVE5_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => BIZ_WAVE5,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'contact_id'  => 1,
    ]);

    for ($i = 1; $i <= $itemsCount; $i++) {
        ServiceOrderItem::withoutGlobalScopes()->create([
            'business_id'      => BIZ_WAVE5,
            'service_order_id' => $os->id,
            'tipo'             => ServiceOrderItem::TIPO_PECA,
            'descricao'        => "Item Wave5 #{$i}",
            'quantidade'       => 1,
            'valor_unitario'   => 100.00 * $i,
        ]);
    }

    return $os;
}

function wave5_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_WAVE5_PREFIX . $suffix . '%')
        ->pluck('id')
        ->toArray();

    if (empty($vehicles)) {
        return;
    }

    $osIds = ServiceOrder::withoutGlobalScopes()
        ->whereIn('vehicle_id', $vehicles)
        ->pluck('id')
        ->toArray();

    if (! empty($osIds)) {
        ServiceOrderItem::withoutGlobalScopes()
            ->whereIn('service_order_id', $osIds)
            ->forceDelete();
        ServiceOrder::withoutGlobalScopes()
            ->whereIn('id', $osIds)
            ->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// Inertia payload — Show + Edit incluem items[]
// ---------------------------------------------------------------------------

it('Inertia GET /ordens-servico/{id} inclui items[] no payload (Show.tsx consumer)', function () {
    session(['user.business_id' => BIZ_WAVE5]);
    $os = wave5_criaOsComItens('A', itemsCount: 3);

    $user = User::factory()->create(['business_id' => BIZ_WAVE5]);
    $user->givePermissionTo('superadmin');

    // Sem Accept: application/json → Inertia branch (HTML wrapper) — testamos via
    // X-Inertia header que devolve o payload JSON do Inertia::render diretamente.
    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/oficina-auto/ordens-servico/{$os->id}");

    $response->assertOk();
    $props = $response->json('props') ?? [];

    expect($props)->toHaveKey('order');
    expect($props['order'])->toHaveKey('items');
    expect($props['order']['items'])->toHaveCount(3);
    expect($props['order']['items'][0])->toHaveKeys([
        'id', 'tipo', 'descricao', 'quantidade', 'valor_unitario', 'valor_total',
    ]);
    expect($props['order']['items'][0]['tipo'])->toBe('peca');
})->afterEach(fn () => wave5_cleanup('A'));

it('Inertia GET /ordens-servico/{id}/edit inclui items[] no payload (Edit.tsx consumer)', function () {
    session(['user.business_id' => BIZ_WAVE5]);
    $os = wave5_criaOsComItens('B', itemsCount: 2);

    $user = User::factory()->create(['business_id' => BIZ_WAVE5]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/oficina-auto/ordens-servico/{$os->id}/edit");

    $response->assertOk();
    $props = $response->json('props') ?? [];

    expect($props)->toHaveKey('order');
    expect($props['order'])->toHaveKey('items');
    expect($props['order']['items'])->toHaveCount(2);
    expect($props['order']['items'][0])->toHaveKeys([
        'id', 'tipo', 'descricao', 'quantidade', 'valor_unitario', 'valor_total',
    ]);
})->afterEach(fn () => wave5_cleanup('B'));

it('Inertia GET /ordens-servico/{id} sem items retorna items=[] (não null)', function () {
    session(['user.business_id' => BIZ_WAVE5]);
    $os = wave5_criaOsComItens('C', itemsCount: 0);

    $user = User::factory()->create(['business_id' => BIZ_WAVE5]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/oficina-auto/ordens-servico/{$os->id}");

    $response->assertOk();
    $props = $response->json('props') ?? [];

    expect($props['order']['items'])->toBeArray();
    expect($props['order']['items'])->toHaveCount(0);
})->afterEach(fn () => wave5_cleanup('C'));

// ---------------------------------------------------------------------------
// HTTP DELETE — happy path + cross-OS guard
// ---------------------------------------------------------------------------

it('HTTP DELETE /items/{item} autenticado retorna 200 com deleted=true', function () {
    session(['user.business_id' => BIZ_WAVE5]);
    $os = wave5_criaOsComItens('D', itemsCount: 1);
    $item = $os->items()->first();

    $user = User::factory()->create(['business_id' => BIZ_WAVE5]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->deleteJson("/oficina-auto/ordens-servico/{$os->id}/items/{$item->id}");

    $response->assertOk();
    expect($response->json('deleted'))->toBeTrue();
    expect($response->json('id'))->toBe($item->id);

    // Soft delete — registro existe mas trashed.
    $fresh = ServiceOrderItem::withoutGlobalScopes()->withTrashed()->find($item->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->trashed())->toBeTrue();
})->afterEach(fn () => wave5_cleanup('D'));

it('HTTP DELETE cross-OS (item de OS1 via URL OS2) → 404 abort guard', function () {
    session(['user.business_id' => BIZ_WAVE5]);
    $os1 = wave5_criaOsComItens('E1', itemsCount: 1);
    $os2 = wave5_criaOsComItens('E2', itemsCount: 0);
    $itemOs1 = $os1->items()->first();

    $user = User::factory()->create(['business_id' => BIZ_WAVE5]);
    $user->givePermissionTo('superadmin');

    // Tenta DELETE via OS2 com item.service_order_id=os1 → abort_unless 404
    $response = $this->actingAs($user)
        ->deleteJson("/oficina-auto/ordens-servico/{$os2->id}/items/{$itemOs1->id}");

    $response->assertNotFound();

    // Item de OS1 deve continuar existindo (não foi deletado por erro de auth)
    $fresh = ServiceOrderItem::withoutGlobalScopes()->find($itemOs1->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->trashed())->toBeFalse();
})->afterEach(function () {
    wave5_cleanup('E1');
    wave5_cleanup('E2');
});
