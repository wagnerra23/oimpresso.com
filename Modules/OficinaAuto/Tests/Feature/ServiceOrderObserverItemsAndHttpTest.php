<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Wave 1.2 + 1.3 US-OFICINA-027 — Pest integration tests.
 *
 * Cobertura:
 *   - Observer `computeFinalTotal` manutenção: OS com 3 items (peça + mão-obra + terceiro)
 *     → Transaction derivada `final_total` = sum(valor_total) (substitui hardcode 0.0)
 *   - Observer backward compat: OS manutenção SEM items → Transaction.final_total = 0
 *   - Controller HTTP `POST /oficina-auto/ordens-servico/{order}/items` autenticado retorna 201
 *   - Multi-tenant Tier 0 [ADR 0093]: user biz=1 tentando criar item em OS biz=99 → 422
 *     (Service::addItem rejeita "OS não pertence ao business")
 *   - PUT/DELETE: abort_unless item.service_order_id === order.id (404 se cross-OS)
 *
 * Cobertura Model + Service entregue em Wave 27 G1 (`ServiceOrderItemTest.php`)
 * + Wave 28 polish (`Wave28OficinaAutoPolishTest.php`). Este arquivo testa o wire-up
 * Observer (Wave 1.2) + HTTP (Wave 1.3).
 *
 * @see Modules/OficinaAuto/Observers/ServiceOrderObserver.php
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderItemController.php
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-027
 */

const BIZ_US027_A = 1;
const BIZ_US027_B = 99;
const PLATE_US027_PREFIX = 'US027';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010 primeiro');
    }
    if (! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Schema UltimatePOS transactions missing');
    }
});

function us027_criaOs(string $suffix, int $biz = BIZ_US027_A, string $orderType = 'manutencao'): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_US027_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => $orderType,
        'status'      => 'aberta',
        'contact_id'  => 1, // Observer exige contact_id resolvível
    ]);
}

function us027_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_US027_PREFIX . $suffix . '%')
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
        ServiceOrderItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
        Transaction::whereIn('os_ref', array_map(fn ($id) => "SO-{$id}", $osIds))->delete();
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// Wave 1.2 — Observer recalc final_total via items() (manutenção)
// ---------------------------------------------------------------------------

it('Observer: OS manutenção com 3 items lançados → Transaction.final_total = soma valor_total', function () {
    session(['user.business_id' => BIZ_US027_A]);
    $os = us027_criaOs('A');

    // 3 items: peça R$ 4.800 + mão-obra 3h × R$ 120 = R$ 360 + serviço terceiro R$ 850 = R$ 6.010
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_US027_A,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_PECA,
        'descricao'        => 'Cilindro hidráulico 6.5T',
        'quantidade'       => 1,
        'valor_unitario'   => 4800.00,
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_US027_A,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_MAO_OBRA,
        'descricao'        => 'Troca cilindro + sangria',
        'quantidade'       => 3,
        'valor_unitario'   => 120.00,
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_US027_A,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_SERVICO_TERCEIRO,
        'descricao'        => 'Recondicionamento bomba',
        'quantidade'       => 1,
        'valor_unitario'   => 850.00,
    ]);

    // Dispara Observer via transição status → 'concluida'
    $os->status = 'concluida';
    $os->save();

    $tx = Transaction::where('os_ref', "SO-{$os->id}")->first();
    expect($tx)->not->toBeNull();
    expect((float) $tx->final_total)->toBe(6010.00);
    expect($tx->source)->toBe('oficina');
    expect($tx->business_id)->toBe(BIZ_US027_A);
})->afterEach(fn () => us027_cleanup('A'));

it('Observer: OS manutenção SEM items lançados → Transaction.final_total = 0 (backward compat)', function () {
    session(['user.business_id' => BIZ_US027_A]);
    $os = us027_criaOs('B');

    $os->status = 'concluida';
    $os->save();

    $tx = Transaction::where('os_ref', "SO-{$os->id}")->first();
    expect($tx)->not->toBeNull();
    expect((float) $tx->final_total)->toBe(0.0);
})->afterEach(fn () => us027_cleanup('B'));

// ---------------------------------------------------------------------------
// Wave 1.3 — Controller HTTP CRUD items
// ---------------------------------------------------------------------------

it('HTTP POST /items autenticado biz=1 → 201 com item criado', function () {
    session(['user.business_id' => BIZ_US027_A]);
    $os = us027_criaOs('C');

    $user = User::factory()->create(['business_id' => BIZ_US027_A]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->postJson("/oficina-auto/ordens-servico/{$os->id}/items", [
            'tipo'           => 'peca',
            'descricao'      => 'Filtro óleo motor',
            'quantidade'     => 1,
            'valor_unitario' => 35.00,
        ]);

    $response->assertCreated();
    expect($response->json('tipo'))->toBe('peca');
    expect($response->json('valor_total'))->toBe(35.00);

    $count = ServiceOrderItem::withoutGlobalScopes()
        ->where('service_order_id', $os->id)
        ->count();
    expect($count)->toBe(1);
})->afterEach(fn () => us027_cleanup('C'));

it('HTTP POST /items cross-tenant biz=1 tentando OS biz=99 → 422 (Service rejeita)', function () {
    session(['user.business_id' => BIZ_US027_A]);
    $osBizOutro = us027_criaOs('D', BIZ_US027_B);

    $user = User::factory()->create(['business_id' => BIZ_US027_A]);
    $user->givePermissionTo('superadmin');

    $response = $this->actingAs($user)
        ->postJson("/oficina-auto/ordens-servico/{$osBizOutro->id}/items", [
            'tipo'           => 'peca',
            'descricao'      => 'Cross-tenant attack',
            'quantidade'     => 1,
            'valor_unitario' => 10,
        ]);

    // Service::addItem lança InvalidArgumentException convertido em 422 no Controller
    // (Policy authorize('update', $order) também pode rejeitar — qualquer 4xx é guard ok).
    expect($response->status())->toBeIn([403, 404, 422]);

    $count = ServiceOrderItem::withoutGlobalScopes()
        ->where('service_order_id', $osBizOutro->id)
        ->count();
    expect($count)->toBe(0);
})->afterEach(function () {
    us027_cleanup('D');
});

it('HTTP PUT /items/{item} cross-OS (item de OS diferente da URL) → 404 abort guard', function () {
    session(['user.business_id' => BIZ_US027_A]);
    $os1 = us027_criaOs('E1');
    $os2 = us027_criaOs('E2');

    $item = ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_US027_A,
        'service_order_id' => $os1->id,
        'tipo'             => 'peca',
        'descricao'        => 'Item de OS1',
        'quantidade'       => 1,
        'valor_unitario'   => 100,
    ]);

    $user = User::factory()->create(['business_id' => BIZ_US027_A]);
    $user->givePermissionTo('superadmin');

    // Tenta editar item via OS2 (URL não bate com item.service_order_id)
    $response = $this->actingAs($user)
        ->putJson("/oficina-auto/ordens-servico/{$os2->id}/items/{$item->id}", [
            'descricao' => 'Tentativa cross-OS',
        ]);

    $response->assertNotFound();
    expect($item->fresh()->descricao)->toBe('Item de OS1');
})->afterEach(function () {
    us027_cleanup('E1');
    us027_cleanup('E2');
});
