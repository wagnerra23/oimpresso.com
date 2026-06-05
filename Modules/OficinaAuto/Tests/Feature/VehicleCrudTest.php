<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * CRUD básico de Vehicle (US-OFICINA-001 — ADR 0137).
 *
 * Tests biz=1 (Wagner WR2) conforme ADR 0101 — nunca biz=4 (cliente ROTA LIVRE).
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope em Vehicle filtra automaticamente.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

defined('BIZ_WAGNER') || define('BIZ_WAGNER', 1);

beforeEach(function () {
    // CI SQLite :memory: sem migrate UltimatePOS — testes precisam schema MySQL completo
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema OficinaAuto requer MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles table missing — rode módulo OficinaAuto migrate primeiro');
    }
});

it('cria veículo com placa única (caso Martinho — caçamba avulsa)', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $vehicle = Vehicle::create([
        'business_id'      => BIZ_WAGNER,
        'plate'            => 'TEST001',
        'vehicle_type'     => 'cacamba_estacionaria',
        'manufacture_year' => 2018,
        'color'            => 'Amarelo',
    ]);

    expect($vehicle->id)->toBeGreaterThan(0);
    expect($vehicle->plate)->toBe('TEST001');
    expect($vehicle->business_id)->toBe(BIZ_WAGNER);
    expect($vehicle->secondary_plate)->toBeNull();
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST001')->forceDelete();
});

it('cria veículo multi-placa (caso Vargas — cavalo+reboque)', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $vehicle = Vehicle::create([
        'business_id'       => BIZ_WAGNER,
        'plate'             => 'TEST002',
        'secondary_plate'   => 'REB001',
        'chassis'           => '9BWZZZ377VT004251',
        'secondary_chassis' => '9BWZZZ377VT004252',
        'vehicle_type'      => 'cavalo',
        'manufacture_year'  => 2020,
    ]);

    expect($vehicle->secondary_plate)->toBe('REB001');
    expect($vehicle->vehicle_type)->toBe('cavalo');
    expect($vehicle->secondary_chassis)->toBe('9BWZZZ377VT004252');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST002')->forceDelete();
});

it('lista veículos (Vehicle::all filtrado por scope)', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    Vehicle::create([
        'business_id'  => BIZ_WAGNER,
        'plate'        => 'TEST003',
        'vehicle_type' => 'automovel',
    ]);

    $vehicles = Vehicle::where('plate', 'TEST003')->get();
    expect($vehicles)->toHaveCount(1);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST003')->forceDelete();
});

it('atualiza veículo', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $vehicle = Vehicle::create([
        'business_id'  => BIZ_WAGNER,
        'plate'        => 'TEST004',
        'vehicle_type' => 'automovel',
        'color'        => 'Preto',
    ]);

    $vehicle->update(['color' => 'Branco']);

    expect($vehicle->fresh()->color)->toBe('Branco');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST004')->forceDelete();
});

it('soft delete preserva registro', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $vehicle = Vehicle::create([
        'business_id'  => BIZ_WAGNER,
        'plate'        => 'TEST005',
        'vehicle_type' => 'automovel',
    ]);
    $id = $vehicle->id;
    $vehicle->delete();

    // Global scope + soft delete: nada retorna em Vehicle::find()
    expect(Vehicle::find($id))->toBeNull();

    // withTrashed retorna
    $trashed = Vehicle::withoutGlobalScopes()->withTrashed()->find($id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST005')->forceDelete();
});

it('preserva legacy_id para futuro importer Firebird (US-OFICINA-002)', function () {
    session(['user.business_id' => BIZ_WAGNER]);

    $vehicle = Vehicle::create([
        'business_id'  => BIZ_WAGNER,
        'plate'        => 'TEST006',
        'vehicle_type' => 'caminhao',
        'legacy_id'    => 'FB-12345', // CODIGO Firebird EQUIPAMENTO_VEICULO
    ]);

    expect($vehicle->legacy_id)->toBe('FB-12345');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'TEST006')->forceDelete();
});
