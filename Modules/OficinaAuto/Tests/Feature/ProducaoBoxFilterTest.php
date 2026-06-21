<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Filtro por box/mecânico no kanban Produção · Oficina (modelo reparo).
 *
 * Eixo de recurso construído SOBRE campos existentes (Wave 2.1 US-OFICINA-027):
 * `service_orders.box_label` (texto livre) + `assigned_user_id`. Sem schema novo
 * (ADR 0105: box texto livre até sinal qualificado). Espelha a query do
 * `ProducaoOficinaController::loadVehicles` (whereHas currentRental box_label) +
 * `buildFilterOptions` (distinct box_label das OS ativas).
 *
 * Cobre:
 *   1. Filtro box='Box 1' retorna só os veículos cuja OS ativa está nesse box
 *   2. Multi-tenant Tier 0 — box 'Box 1' do biz=99 NÃO vaza pro biz=1
 *   3. buildFilterOptions: boxes distintos só das OS ativas (terminal não entra)
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS). Validação na query.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php
 */

const BIZ_BOX_TEST = 1;
const BIZ_BOX_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'box_label')) {
        $this->markTestSkipped('service_orders.box_label ausente — rode migrate primeiro');
    }
});

/**
 * Cria veículo com OS ativa apontada por current_rental_id, com box_label dado.
 */
function boxCreateVehicleWithOs(int $bizId, ?string $box, string $status = 'locada'): Vehicle
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $bizId,
        'plate'        => 'BOX' . substr((string) random_int(1000, 9999), 0, 4),
        'vehicle_type' => 'caminhao',
        'current_status' => $status,
    ]);

    $order = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $bizId,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao', // locação erradicada (ADR 0265); order_type incidental — teste é de box_label
        'status'      => 'aberta',
        'entered_at'  => now(),
        'box_label'   => $box,
    ]);

    $vehicle->forceFill(['current_rental_id' => $order->id])->save();

    return $vehicle;
}

function boxCleanup(int $bizId): void
{
    Vehicle::withoutGlobalScopes()->where('business_id', $bizId)->where('plate', 'like', 'BOX%')->forceDelete();
    ServiceOrder::withoutGlobalScopes()->where('business_id', $bizId)->where('order_type', 'manutencao')->forceDelete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. filtro box="Box 1" retorna só veículos cuja OS ativa está nesse box', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1');
    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1');
    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 2');
    boxCreateVehicleWithOs(BIZ_BOX_TEST, null);

    // Mesma query do controller (loadVehicles → whereHas currentRental box_label)
    $filtered = Vehicle::query()
        ->whereHas('currentRental', fn ($q) => $q->where('box_label', 'Box 1'))
        ->count();

    expect($filtered)->toBe(2);
})->afterEach(fn () => boxCleanup(BIZ_BOX_TEST));

it('2. multi-tenant Tier 0 — box "Box 1" do biz=99 NÃO vaza pro biz=1', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1');
    boxCreateVehicleWithOs(BIZ_BOX_FICTICIO, 'Box 1');

    // Global scope do Vehicle filtra biz=1 automaticamente
    $filtered = Vehicle::query()
        ->whereHas('currentRental', fn ($q) => $q->where('box_label', 'Box 1'))
        ->count();

    expect($filtered)->toBe(1);
})->afterEach(function () {
    boxCleanup(BIZ_BOX_TEST);
    boxCleanup(BIZ_BOX_FICTICIO);
});

it('3. buildFilterOptions retorna boxes distintos só das OS ativas', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1');
    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1'); // duplicado → distinct colapsa
    boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Elevador 2');

    // OS terminal NÃO deve aparecer nas opções
    $terminalVeh = boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 9');
    $terminalVeh->currentRental->forceFill(['status' => 'concluida'])->save();

    $boxes = ServiceOrder::query()
        ->whereNotIn('status', ['concluida', 'cancelada', 'recolhida'])
        ->whereNotNull('box_label')
        ->where('box_label', '!=', '')
        ->distinct()
        ->orderBy('box_label')
        ->pluck('box_label')
        ->values()
        ->all();

    expect($boxes)->toBe(['Box 1', 'Elevador 2']);
})->afterEach(fn () => boxCleanup(BIZ_BOX_TEST));
