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
 *   2. Multi-tenant Tier 0 — box 'Box 1' do biz=2 NÃO vaza pro biz=1
 *   3. buildFilterOptions: boxes distintos só das OS ativas (terminal não entra)
 *
 * Pattern: skip SQLite (ADR 0101 — schema MySQL UltimatePOS). Validação na query.
 *
 * @see Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php
 */

const BIZ_BOX_TEST = 1;
const BIZ_BOX_FICTICIO = 2; // 2º tenant do seed da lane (pest-mysql-setup); 99 não existe lá e quebra fk_vehicles_business

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
    // Só as OS dos veículos deste arquivo — antes apagava toda OS 'manutencao' do tenant.
    $vehicleIds = Vehicle::withoutGlobalScopes()->where('business_id', $bizId)->where('plate', 'like', 'BOX%')->pluck('id');
    ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicleIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->forceDelete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. filtro box="Box 1" retorna só veículos cuja OS ativa está nesse box', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    $ids = collect([
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1'),
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1'),
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 2'),
        boxCreateVehicleWithOs(BIZ_BOX_TEST, null),
    ])->pluck('id');

    // Mesma query do controller (loadVehicles → whereHas currentRental box_label),
    // sobre os veículos deste teste: o tenant da lane é compartilhado com outros arquivos.
    $filtered = Vehicle::query()
        ->whereIn('id', $ids)
        ->whereHas('currentRental', fn ($q) => $q->where('box_label', 'Box 1'))
        ->count();

    expect($filtered)->toBe(2);
})->afterEach(fn () => boxCleanup(BIZ_BOX_TEST));

it('2. multi-tenant Tier 0 — box "Box 1" do biz=2 NÃO vaza pro biz=1', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    $ids = [
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1')->id,
        boxCreateVehicleWithOs(BIZ_BOX_FICTICIO, 'Box 1')->id,
    ];

    // Global scope do Vehicle filtra biz=1 automaticamente — o veículo do biz=2 está no
    // universo do whereIn e mesmo assim tem de ficar de fora.
    $filtered = Vehicle::query()
        ->whereIn('id', $ids)
        ->whereHas('currentRental', fn ($q) => $q->where('box_label', 'Box 1'))
        ->count();

    expect($filtered)->toBe(1);
})->afterEach(function () {
    boxCleanup(BIZ_BOX_TEST);
    boxCleanup(BIZ_BOX_FICTICIO);
});

it('3. buildFilterOptions retorna boxes distintos só das OS ativas', function () {
    session(['user.business_id' => BIZ_BOX_TEST]);

    $veiculos = collect([
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1'),
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 1'), // duplicado → distinct colapsa
        boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Elevador 2'),
    ]);

    // OS terminal NÃO deve aparecer nas opções
    $terminalVeh = boxCreateVehicleWithOs(BIZ_BOX_TEST, 'Box 9');
    $terminalVeh->currentRental->forceFill(['status' => 'concluida'])->save();
    $veiculos->push($terminalVeh);

    // Sobre as OS deste teste: o tenant da lane é compartilhado com outros arquivos.
    $boxes = ServiceOrder::query()
        ->whereIn('vehicle_id', $veiculos->pluck('id'))
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
