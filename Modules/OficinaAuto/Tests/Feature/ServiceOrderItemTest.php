<?php

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OSH-04 (OficinaAuto/ServiceOrders/Show) — total/itens exibidos (peça×qty + hora×horas)
//   UC-OED-02 (OficinaAuto/ServiceOrders/Edit) — adicionar/editar item recalcula o Total OS (Observer)

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\ServiceOrderItemService;

uses(Tests\TestCase::class);

/**
 * W27 G1 — Smoke ServiceOrderItem entity + Service.
 *
 * Tests biz=1 conforme ADR 0101 (biz=99 só usado pra isolamento cross-tenant).
 *
 * @see Modules/OficinaAuto/Entities/ServiceOrderItem.php
 * @see Modules/OficinaAuto/Services/ServiceOrderItemService.php
 */

const BIZ_W27 = 1;
const BIZ_W27_OUTRO = 99;
const PLATE_W27_PREFIX = 'W27ITM';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oficina_service_order_items')) {
        $this->markTestSkipped('Rode migration 2026_05_17_000010_create_oficina_service_order_items_table primeiro');
    }
});

function w27_criaOsParaItens(string $suffix, int $biz = BIZ_W27): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_W27_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);
}

function w27_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_W27_PREFIX . $suffix)
        ->pluck('id')
        ->toArray();

    if (! empty($vehicles)) {
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
}

// ---------------------------------------------------------------------------
// Entity tests
// ---------------------------------------------------------------------------

it('cria ServiceOrderItem com auto-cálculo valor_total', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('A');

    $item = ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_W27,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_PECA,
        'descricao'        => 'Pastilha freio dianteira',
        'quantidade'       => 1,
        'valor_unitario'   => 180.00,
    ]);

    expect($item->id)->toBeGreaterThan(0);
    expect((float) $item->valor_total)->toBe(180.00); // auto-calculado no creating
    expect($item->business_id)->toBe(BIZ_W27);
})->afterEach(fn () => w27_cleanup('A'));

it('recalcula valor_total no update quando quantidade muda', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('B');

    $item = ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_W27,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_MAO_OBRA,
        'descricao'        => 'Troca pastilha',
        'quantidade'       => 0.5,
        'valor_unitario'   => 120.00,
    ]);
    expect((float) $item->valor_total)->toBe(60.00);

    $item->update(['quantidade' => 1.0]);
    expect((float) $item->fresh()->valor_total)->toBe(120.00);
})->afterEach(fn () => w27_cleanup('B'));

it('escopa por business_id global scope (biz=99 não enxerga itens biz=1)', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('C', BIZ_W27);
    ServiceOrderItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_W27,
        'service_order_id' => $os->id,
        'tipo'             => ServiceOrderItem::TIPO_PECA,
        'descricao'        => 'Filtro óleo',
        'quantidade'       => 1,
        'valor_unitario'   => 35.00,
    ]);

    // Switch session pra biz=99 — global scope deve esconder o item
    session(['user.business_id' => BIZ_W27_OUTRO]);
    $visiveis = ServiceOrderItem::where('service_order_id', $os->id)->get();
    expect($visiveis)->toHaveCount(0);

    // SUPERADMIN bypass mostra os 1
    $todos = ServiceOrderItem::withoutGlobalScopes()->where('service_order_id', $os->id)->get();
    expect($todos)->toHaveCount(1);
})->afterEach(fn () => w27_cleanup('C'));

// ---------------------------------------------------------------------------
// Service tests
// ---------------------------------------------------------------------------

it('Service::addItem cria peça + mão-de-obra e recalcularTotal soma correto', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('D');
    $service = new ServiceOrderItemService();

    $service->addItem(BIZ_W27, $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'Pastilha freio',
        'quantidade'     => 1,
        'valor_unitario' => 180.00,
    ]);
    $service->addItem(BIZ_W27, $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_MAO_OBRA,
        'descricao'      => 'Troca pastilha (0.5h)',
        'quantidade'     => 0.5,
        'valor_unitario' => 120.00,
    ]);
    $service->addItem(BIZ_W27, $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_SERVICO_TERCEIRO,
        'descricao'      => 'Alinhamento (terceirizado)',
        'quantidade'     => 1,
        'valor_unitario' => 80.00,
    ]);

    $total = $service->recalcularTotal($os->id);
    expect($total)->toBe(320.00); // 180 + 60 + 80
})->afterEach(fn () => w27_cleanup('D'));

it('Service::addItem rejeita tipo inválido', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('E');
    $service = new ServiceOrderItemService();

    expect(fn () => $service->addItem(BIZ_W27, $os->id, [
        'tipo'           => 'invalido',
        'descricao'      => 'X',
        'quantidade'     => 1,
        'valor_unitario' => 10,
    ]))->toThrow(InvalidArgumentException::class, "tipo inválido");
})->afterEach(fn () => w27_cleanup('E'));

it('Service::addItem rejeita OS de outro business (cross-tenant defense)', function () {
    session(['user.business_id' => BIZ_W27]);
    $osBiz99 = w27_criaOsParaItens('F', BIZ_W27_OUTRO);
    $service = new ServiceOrderItemService();

    // Tentando adicionar item biz=1 numa OS biz=99 → deve rejeitar
    expect(fn () => $service->addItem(BIZ_W27, $osBiz99->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'X',
        'quantidade'     => 1,
        'valor_unitario' => 10,
    ]))->toThrow(InvalidArgumentException::class, 'não pertence ao business');
})->afterEach(fn () => w27_cleanup('F'));

it('Service::breakdownPorTipo retorna agregado correto', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('G');
    $service = new ServiceOrderItemService();

    $service->addItem(BIZ_W27, $os->id, ['tipo' => ServiceOrderItem::TIPO_PECA, 'descricao' => 'P1', 'quantidade' => 2, 'valor_unitario' => 50]);
    $service->addItem(BIZ_W27, $os->id, ['tipo' => ServiceOrderItem::TIPO_PECA, 'descricao' => 'P2', 'quantidade' => 1, 'valor_unitario' => 30]);
    $service->addItem(BIZ_W27, $os->id, ['tipo' => ServiceOrderItem::TIPO_MAO_OBRA, 'descricao' => 'M1', 'quantidade' => 1, 'valor_unitario' => 100]);

    $bd = $service->breakdownPorTipo($os->id);
    expect($bd['peca'])->toBe(130.00); // 100 + 30
    expect($bd['mao_obra'])->toBe(100.00);
    expect($bd['servico_terceiro'])->toBe(0.00);
    expect($bd['total'])->toBe(230.00);
})->afterEach(fn () => w27_cleanup('G'));

it('Service::listarPorTipo filtra estritamente', function () {
    session(['user.business_id' => BIZ_W27]);
    $os = w27_criaOsParaItens('H');
    $service = new ServiceOrderItemService();

    $service->addItem(BIZ_W27, $os->id, ['tipo' => ServiceOrderItem::TIPO_PECA, 'descricao' => 'P', 'quantidade' => 1, 'valor_unitario' => 10]);
    $service->addItem(BIZ_W27, $os->id, ['tipo' => ServiceOrderItem::TIPO_MAO_OBRA, 'descricao' => 'M', 'quantidade' => 1, 'valor_unitario' => 20]);

    expect($service->listarPorTipo($os->id, ServiceOrderItem::TIPO_PECA))->toHaveCount(1);
    expect($service->listarPorTipo($os->id, ServiceOrderItem::TIPO_MAO_OBRA))->toHaveCount(1);
    expect($service->listarPorTipo($os->id, ServiceOrderItem::TIPO_SERVICO_TERCEIRO))->toHaveCount(0);
})->afterEach(fn () => w27_cleanup('H'));
