<?php

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OSH-01 (OficinaAuto/ServiceOrders/Show)  — ver a OS como fonte-da-verdade (resumo + itens)
//   UC-OED-01 (OficinaAuto/ServiceOrders/Edit)  — editar e salvar retorna pro Show
//   UC-OCR-01 (OficinaAuto/ServiceOrders/Create) — abrir OS com veículo existente redireciona pro Show

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * CRUD básico de ServiceOrder (US-OFICINA-001 — ADR 0137).
 *
 * Status livre na V0. FSM canônica (3 estados Simples + 5 estados Complexa)
 * chega em US-OFICINA-003 (ADR 0129).
 *
 * Tests biz=1 conforme ADR 0101.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0129-state-machine-canonica-fsm-rbac.md
 */

const BIZ_WAGNER_SO = 1;
const BIZ_FICTICIO_SO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('service_orders/vehicles tables missing — rode OficinaAuto migrate primeiro');
    }
});

function createTestVehicle(string $plate): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup de teste
        'business_id'  => BIZ_WAGNER_SO,
        'plate'        => $plate,
        'vehicle_type' => 'automovel',
    ]);
}

it('cria OS Simples (caso Martinho — fluxo 3 estados)', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC001');

    $os = ServiceOrder::create([
        'business_id'        => BIZ_WAGNER_SO,
        'vehicle_id'         => $vehicle->id,
        'status'             => 'aberta',
        'entered_at'         => now(),
        'mileage_at_service' => 45000,
        'notes'              => 'Cliente reclama de barulho na suspensão',
    ]);

    expect($os->id)->toBeGreaterThan(0);
    expect($os->status)->toBe('aberta');
    expect($os->vehicle_id)->toBe($vehicle->id);
    expect($os->business_id)->toBe(BIZ_WAGNER_SO);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC001'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC001')->forceDelete();
});

it('relaciona OS → Vehicle (eager load)', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC002');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_SO,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);

    $loaded = ServiceOrder::with('vehicle')->find($os->id);
    expect($loaded->vehicle)->not->toBeNull();
    expect($loaded->vehicle->plate)->toBe('SOC002');
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC002'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC002')->forceDelete();
});

it('relaciona Vehicle → ServiceOrders (hasMany)', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC003');
    ServiceOrder::create(['business_id' => BIZ_WAGNER_SO, 'vehicle_id' => $vehicle->id, 'status' => 'aberta']);
    ServiceOrder::create(['business_id' => BIZ_WAGNER_SO, 'vehicle_id' => $vehicle->id, 'status' => 'concluida']);

    $fresh = Vehicle::with('serviceOrders')->find($vehicle->id);
    expect($fresh->serviceOrders)->toHaveCount(2);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC003'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC003')->forceDelete();
});

it('atualiza status da OS (status livre na V0 — FSM em US-OFICINA-003)', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC004');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_SO,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);

    $os->update(['status' => 'em_servico', 'completed_at' => null]);
    expect($os->fresh()->status)->toBe('em_servico');

    $os->update(['status' => 'concluida', 'completed_at' => now()]);
    expect($os->fresh()->status)->toBe('concluida');
    expect($os->fresh()->completed_at)->not->toBeNull();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC004'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC004')->forceDelete();
});

it('isolamento multi-tenant — OS biz=1 não aparece com session biz=99', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC005');
    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER_SO,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);

    session(['user.business_id' => BIZ_FICTICIO_SO]);
    $resultado = ServiceOrder::where('id', $os->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC005'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC005')->forceDelete();
});

it('soft delete preserva OS', function () {
    session(['user.business_id' => BIZ_WAGNER_SO]);

    $vehicle = createTestVehicle('SOC006');
    $os = ServiceOrder::create([
        'business_id' => BIZ_WAGNER_SO,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);
    $id = $os->id;

    $os->delete();
    expect(ServiceOrder::find($id))->toBeNull();

    $trashed = ServiceOrder::withoutGlobalScopes()->withTrashed()->find($id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
})->afterEach(function () {
    ServiceOrder::withoutGlobalScopes()->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', 'SOC006'))->forceDelete();
    Vehicle::withoutGlobalScopes()->where('plate', 'SOC006')->forceDelete();
});
