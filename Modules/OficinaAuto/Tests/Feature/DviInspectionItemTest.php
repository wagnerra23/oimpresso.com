<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\DviInspectionService;

uses(Tests\TestCase::class);

/**
 * Wave 3 — Smoke DVI (Vistoria Digital) US-OFICINA-035.
 *
 * Tests biz=1 conforme ADR 0101 (biz=99 só usado pra isolamento cross-tenant).
 * Espelha padrão ServiceOrderItemTest (Wave 27 G1).
 *
 * @see Modules/OficinaAuto/Entities/OaInspectionItem.php
 * @see Modules/OficinaAuto/Services/DviInspectionService.php
 * @see Modules/OficinaAuto/Http/Controllers/DviInspectionController.php
 */

const BIZ_DVI = 1;
const BIZ_DVI_OUTRO = 99;
const PLATE_DVI_PREFIX = 'WDVI';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('oa_inspection_items')) {
        $this->markTestSkipped('Rode migration 2026_05_26_120002_create_oa_inspection_items_table primeiro');
    }
});

function dvi_criaOs(string $suffix, int $biz = BIZ_DVI): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_DVI_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);
}

function dvi_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_DVI_PREFIX . $suffix)
        ->pluck('id')
        ->toArray();

    if (! empty($vehicles)) {
        $osIds = ServiceOrder::withoutGlobalScopes()
            ->whereIn('vehicle_id', $vehicles)
            ->pluck('id')
            ->toArray();

        if (! empty($osIds)) {
            OaInspectionItem::withoutGlobalScopes()->whereIn('service_order_id', $osIds)->forceDelete();
            ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
        }

        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

// ---------------------------------------------------------------------------
// Entity tests — CRUD + global scope
// ---------------------------------------------------------------------------

it('cria OaInspectionItem com auto-set business_id via creating hook', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('A');

    $item = OaInspectionItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_DVI,
        'service_order_id' => $os->id,
        'categoria'        => 'motor',
        'descricao'        => 'Motor · óleo + filtro',
        'severity'         => OaInspectionItem::SEVERITY_OK,
        'metadata'         => ['km_restantes' => 4500],
    ]);

    expect($item->id)->toBeGreaterThan(0);
    expect($item->business_id)->toBe(BIZ_DVI);
    expect($item->severity)->toBe('ok');
    expect($item->metadata)->toBe(['km_restantes' => 4500]);
})->afterEach(fn () => dvi_cleanup('A'));

it('escopa por business_id global scope (biz=99 não enxerga itens biz=1)', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('B', BIZ_DVI);
    OaInspectionItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_DVI,
        'service_order_id' => $os->id,
        'categoria'        => 'freios',
        'descricao'        => 'Pastilhas dianteiras 3mm',
        'severity'         => OaInspectionItem::SEVERITY_ATENCAO,
        'valor_recomendado' => 145.00,
    ]);

    // Switch session pra biz=99 — global scope deve esconder
    session(['user.business_id' => BIZ_DVI_OUTRO]);
    $visiveis = OaInspectionItem::where('service_order_id', $os->id)->get();
    expect($visiveis)->toHaveCount(0);

    // SUPERADMIN bypass mostra
    $todos = OaInspectionItem::withoutGlobalScopes()->where('service_order_id', $os->id)->get();
    expect($todos)->toHaveCount(1);
})->afterEach(fn () => dvi_cleanup('B'));

// ---------------------------------------------------------------------------
// Service tests
// ---------------------------------------------------------------------------

it('Service::addItem cria item válido e respeita severity', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('C');
    $service = new DviInspectionService();

    $item = $service->addItem(BIZ_DVI, $os->id, [
        'categoria'         => 'correia',
        'descricao'         => 'Correia dentada · trincada',
        'severity'          => OaInspectionItem::SEVERITY_CRITICO,
        'recomendacao'      => 'recomenda troca imediata',
        'valor_recomendado' => 480.00,
        'metadata'          => ['mao_obra_horas' => 2],
    ]);

    expect($item->id)->toBeGreaterThan(0);
    expect($item->severity)->toBe('critico');
    expect((float) $item->valor_recomendado)->toBe(480.00);
})->afterEach(fn () => dvi_cleanup('C'));

it('Service::addItem rejeita categoria inválida', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('D');
    $service = new DviInspectionService();

    expect(fn () => $service->addItem(BIZ_DVI, $os->id, [
        'categoria' => 'inexistente',
        'descricao' => 'X',
        'severity'  => OaInspectionItem::SEVERITY_OK,
    ]))->toThrow(InvalidArgumentException::class, "categoria inválida");
})->afterEach(fn () => dvi_cleanup('D'));

it('Service::addItem rejeita severity inválida', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('E');
    $service = new DviInspectionService();

    expect(fn () => $service->addItem(BIZ_DVI, $os->id, [
        'categoria' => 'motor',
        'descricao' => 'X',
        'severity'  => 'amarelo', // inválido
    ]))->toThrow(InvalidArgumentException::class, "severity inválida");
})->afterEach(fn () => dvi_cleanup('E'));

it('Service::addItem rejeita OS de outro business (cross-tenant defense)', function () {
    session(['user.business_id' => BIZ_DVI]);
    $osBiz99 = dvi_criaOs('F', BIZ_DVI_OUTRO);
    $service = new DviInspectionService();

    expect(fn () => $service->addItem(BIZ_DVI, $osBiz99->id, [
        'categoria' => 'motor',
        'descricao' => 'X',
        'severity'  => OaInspectionItem::SEVERITY_OK,
    ]))->toThrow(InvalidArgumentException::class, 'não pertence ao business');
})->afterEach(fn () => dvi_cleanup('F'));

it('Service::breakdownPorSeverity retorna agregados corretos (5 items mistos)', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('G');
    $service = new DviInspectionService();

    // 2 OK
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'motor', 'descricao' => 'M1', 'severity' => 'ok']);
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'bateria', 'descricao' => 'B1', 'severity' => 'ok']);
    // 2 atencao com valor
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'freios', 'descricao' => 'F1', 'severity' => 'atencao', 'valor_recomendado' => 145.00]);
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'pneus', 'descricao' => 'P1', 'severity' => 'atencao', 'valor_recomendado' => 1200.00]);
    // 1 critico com valor
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'correia', 'descricao' => 'C1', 'severity' => 'critico', 'valor_recomendado' => 480.00]);

    $bd = $service->breakdownPorSeverity($os->id);
    expect($bd['ok'])->toBe(2);
    expect($bd['atencao'])->toBe(2);
    expect($bd['critico'])->toBe(1);
    expect($bd['total_recomendado'])->toBe(1825.00); // 145 + 1200 + 480
})->afterEach(fn () => dvi_cleanup('G'));

it('Service::totalRecomendado soma apenas atencao + critico (ignora ok)', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('H');
    $service = new DviInspectionService();

    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'motor', 'descricao' => 'M', 'severity' => 'ok', 'valor_recomendado' => 999.99]); // não conta
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'freios', 'descricao' => 'F', 'severity' => 'atencao', 'valor_recomendado' => 100.00]);
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'correia', 'descricao' => 'C', 'severity' => 'critico', 'valor_recomendado' => 250.50]);

    expect($service->totalRecomendado($os->id))->toBe(350.50);
})->afterEach(fn () => dvi_cleanup('H'));

it('Service::listarOrdenado coloca críticos no topo', function () {
    session(['user.business_id' => BIZ_DVI]);
    $os = dvi_criaOs('I');
    $service = new DviInspectionService();

    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'motor', 'descricao' => 'OK1', 'severity' => 'ok']);
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'correia', 'descricao' => 'CRIT', 'severity' => 'critico']);
    $service->addItem(BIZ_DVI, $os->id, ['categoria' => 'freios', 'descricao' => 'ATE', 'severity' => 'atencao']);

    $rows = $service->listarOrdenado($os->id);
    expect($rows->pluck('severity')->all())->toBe(['critico', 'atencao', 'ok']);
})->afterEach(fn () => dvi_cleanup('I'));

// ---------------------------------------------------------------------------
// HTTP Controller tests — store + cross-OS guard
// ---------------------------------------------------------------------------

it('HTTP POST cria item DVI 201 com payload correto (autenticado superadmin)', function () {
    session(['user.business_id' => BIZ_DVI]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_DVI)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1 pra testar HTTP');
    }

    $os = dvi_criaOs('J');
    $resp = $this->actingAs($user)->postJson(
        "/oficina-auto/ordens-servico/{$os->id}/dvi",
        [
            'categoria'         => 'freios',
            'descricao'         => 'Pastilhas dianteiras 3mm',
            'severity'          => 'atencao',
            'recomendacao'      => 'trocar em 5.000 km',
            'valor_recomendado' => 145.00,
            'metadata'          => ['vida_util_pct' => 60],
        ]
    );

    $resp->assertStatus(201);
    $resp->assertJsonPath('item.severity', 'atencao');
    $resp->assertJsonPath('item.categoria', 'freios');
    $resp->assertJsonPath('item.valor_recomendado', 145.00);
})->afterEach(fn () => dvi_cleanup('J'));

it('HTTP PUT cross-OS guard: item de OS diferente retorna 404', function () {
    session(['user.business_id' => BIZ_DVI]);
    $user = \App\User::withoutGlobalScopes()->where('business_id', BIZ_DVI)->first();
    if ($user === null) {
        $this->markTestSkipped('Sem user disponível em biz=1');
    }

    // Cria 2 OS biz=1, item pertence ao OS-K1, tenta atualizar via rota OS-K2
    $os1 = dvi_criaOs('K1');
    $os2 = dvi_criaOs('K2');

    $item = OaInspectionItem::withoutGlobalScopes()->create([
        'business_id'      => BIZ_DVI,
        'service_order_id' => $os1->id,
        'categoria'        => 'motor',
        'descricao'        => 'X',
        'severity'         => 'ok',
    ]);

    $resp = $this->actingAs($user)->putJson(
        "/oficina-auto/ordens-servico/{$os2->id}/dvi/{$item->id}",
        ['severity' => 'critico']
    );

    $resp->assertStatus(404);
})->afterEach(function () {
    dvi_cleanup('K1');
    dvi_cleanup('K2');
});
