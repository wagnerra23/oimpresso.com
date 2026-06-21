<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Polish canon Board 2026-06-11 — coluna VALOR da listagem com dado REAL.
 *
 * O accessor `valor_receber` é sempre 0.0 (locação erradicada, ADR 0265); o valor
 * de reparo vive em `oficina_service_order_items.valor_total`. O index() passou a
 * anexar `items_total` via `withSum('items as items_total', 'valor_total')` —
 * 1 subquery agregada, sem N+1 na paginação.
 *
 * Valida a MESMA query do controller (validação direta — não roda HTTP middleware
 * UltimatePOS, pattern ServiceOrderIndexStageFilterTest):
 *   1. OS com itens → items_total = soma de valor_total
 *   2. OS sem itens → items_total NULL (frontend formatBRL(null) → "—")
 *   3. Multi-tenant Tier 0 — itens de OS de outro business não somam (FK por OS)
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (método index)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx (workspace unificado — coluna VALOR da Lista)
 */

const BIZ_ITOTAL = 1;
const PLATE_ITOTAL_PREFIX = 'ITOT';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010 primeiro');
    }
});

function itotal_criaOs(string $suffix, int $biz = BIZ_ITOTAL): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_ITOTAL_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
}

function itotal_cleanup(): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_ITOTAL_PREFIX . '%')
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
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

it('1. withSum items_total soma valor_total dos itens da OS (mesma query do index)', function () {
    session(['user.business_id' => BIZ_ITOTAL]);
    $os = itotal_criaOs('A');

    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_ITOTAL,
        'service_order_id' => $os->id,
        'tipo'             => 'peca',
        'descricao'        => 'Disco de freio',
        'quantidade'       => 2,
        'valor_unitario'   => 300,
    ]);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_ITOTAL,
        'service_order_id' => $os->id,
        'tipo'             => 'mao_obra',
        'descricao'        => 'Troca de discos',
        'quantidade'       => 2,
        'valor_unitario'   => 100,
    ]);

    $row = ServiceOrder::query()
        ->withSum('items as items_total', 'valor_total')
        ->find($os->id);

    expect((float) $row->items_total)->toBe(800.0);
})->afterEach(fn () => itotal_cleanup());

it('2. OS sem itens → items_total NULL (frontend renderiza "—")', function () {
    session(['user.business_id' => BIZ_ITOTAL]);
    $os = itotal_criaOs('B');

    $row = ServiceOrder::query()
        ->withSum('items as items_total', 'valor_total')
        ->find($os->id);

    expect($row->items_total)->toBeNull();
})->afterEach(fn () => itotal_cleanup());

it('3. multi-tenant Tier 0 — global scope filtra a OS de outro business inteira', function () {
    session(['user.business_id' => BIZ_ITOTAL]);

    $osBiz1 = itotal_criaOs('C');
    $osBiz99 = itotal_criaOs('D', 99);

    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => 99,
        'service_order_id' => $osBiz99->id,
        'tipo'             => 'peca',
        'descricao'        => 'Item de outro tenant',
        'quantidade'       => 1,
        'valor_unitario'   => 9999,
    ]);

    $rows = ServiceOrder::query()
        ->withSum('items as items_total', 'valor_total')
        ->whereIn('id', [$osBiz1->id, $osBiz99->id])
        ->get();

    // Global scope (biz=1): só a OS do tenant aparece; a OS biz=99 (e os R$ dela) não vazam.
    expect($rows->pluck('id')->all())->toBe([$osBiz1->id]);
})->afterEach(fn () => itotal_cleanup());
