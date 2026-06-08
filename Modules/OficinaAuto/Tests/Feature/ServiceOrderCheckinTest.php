<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Http\Requests\StoreServiceOrderRequest;

uses(Tests\TestCase::class);

/**
 * Check-in de entrada na OS — US-OFICINA-038 (avarias) + US-OFICINA-039 (combustível).
 *
 * Delta do protótipo Cowork "Nova OS" (oficina-os-page.jsx) — ver
 * memory/requisitos/OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md.
 *
 * Tests biz=1 conforme ADR 0101. Espelha harness DviInspectionItemTest.
 */

const BIZ_CHECKIN = 1;
const PLATE_CHECKIN_PREFIX = 'WCHK';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasColumn('service_orders', 'fuel_level_at_entry')) {
        $this->markTestSkipped('Rode migration 2026_06_02_000010_add_checkin_fields_to_service_orders primeiro');
    }
});

function checkin_criaVeiculo(string $suffix, int $biz = BIZ_CHECKIN): Vehicle
{
    return Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_CHECKIN_PREFIX . $suffix,
        'vehicle_type' => 'automovel',
    ]);
}

function checkin_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', PLATE_CHECKIN_PREFIX . $suffix)
        ->pluck('id')
        ->toArray();

    if (! empty($vehicles)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('vehicle_id', $vehicles)->forceDelete();
        Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
    }
}

// ---------------------------------------------------------------------------
// Entity — fillable + casts (combustível int, avarias array JSON roundtrip)
// ---------------------------------------------------------------------------

it('persiste fuel_level_at_entry e entry_damages (cast array roundtrip)', function () {
    session(['user.business_id' => BIZ_CHECKIN]);
    $vehicle = checkin_criaVeiculo('A');

    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id'         => BIZ_CHECKIN,
        'vehicle_id'          => $vehicle->id,
        'status'              => 'aberta',
        'fuel_level_at_entry' => 35,
        'entry_damages'       => ['Risco porta dir.', 'Amassado para-choque'],
    ]);

    $reloaded = ServiceOrder::withoutGlobalScopes()->findOrFail($os->id);

    expect($reloaded->fuel_level_at_entry)->toBe(35);
    expect($reloaded->entry_damages)->toBe(['Risco porta dir.', 'Amassado para-choque']);
})->afterEach(fn () => checkin_cleanup('A'));

it('aceita check-in vazio (campos nullable)', function () {
    session(['user.business_id' => BIZ_CHECKIN]);
    $vehicle = checkin_criaVeiculo('B');

    $os = ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => BIZ_CHECKIN,
        'vehicle_id'  => $vehicle->id,
        'status'      => 'aberta',
    ]);

    $reloaded = ServiceOrder::withoutGlobalScopes()->findOrFail($os->id);

    expect($reloaded->fuel_level_at_entry)->toBeNull();
    expect($reloaded->entry_damages)->toBeNull();
})->afterEach(fn () => checkin_cleanup('B'));

// ---------------------------------------------------------------------------
// FormRequest — regras de validação do combustível (0–100)
// ---------------------------------------------------------------------------

it('valida fuel_level_at_entry entre 0 e 100', function () {
    $rules = (new StoreServiceOrderRequest())->rules();
    $fuelRule = ['fuel_level_at_entry' => $rules['fuel_level_at_entry']];

    expect(Validator::make(['fuel_level_at_entry' => 150], $fuelRule)->fails())->toBeTrue();
    expect(Validator::make(['fuel_level_at_entry' => -5], $fuelRule)->fails())->toBeTrue();
    expect(Validator::make(['fuel_level_at_entry' => 80], $fuelRule)->fails())->toBeFalse();
    expect(Validator::make(['fuel_level_at_entry' => null], $fuelRule)->fails())->toBeFalse();
});

it('valida entry_damages como array de strings curtas', function () {
    $rules = (new StoreServiceOrderRequest())->rules();
    $damageRules = [
        'entry_damages'   => $rules['entry_damages'],
        'entry_damages.*' => $rules['entry_damages.*'],
    ];

    expect(Validator::make(['entry_damages' => ['Risco', 'Amassado']], $damageRules)->fails())->toBeFalse();
    expect(Validator::make(['entry_damages' => 'nao-array'], $damageRules)->fails())->toBeTrue();
    expect(Validator::make(['entry_damages' => [str_repeat('x', 90)]], $damageRules)->fails())->toBeTrue();
});
