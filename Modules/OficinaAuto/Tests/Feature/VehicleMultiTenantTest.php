<?php

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OSH-03 (OficinaAuto/ServiceOrders/Show)   — dados de outro business não vazam (multi-tenant Tier 0)
//   UC-OED-05 (OficinaAuto/ServiceOrders/Edit)   — vehicle_id de outro business rejeitado server-side
//   UC-OCR-04 (OficinaAuto/ServiceOrders/Create) — vehicle_id/contact_id de outro business rejeitado

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant Tier 0 (ADR 0093) — Vehicle.
 *
 * Dados do biz=1 NÃO podem aparecer em queries com session biz=99 e vice-versa.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa produção) — ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

const BIZ_WAGNER_VEH = 1;
const BIZ_FICTICIO_VEH = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles table missing — rode OficinaAuto migrate primeiro');
    }
});

it('Vehicle biz=1 NÃO aparece com session biz=99', function () {
    session(['user.business_id' => BIZ_WAGNER_VEH]);

    $v = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER_VEH,
        'plate'        => 'MTT001',
        'vehicle_type' => 'automovel',
    ]);

    session(['user.business_id' => BIZ_FICTICIO_VEH]);
    $resultado = Vehicle::where('id', $v->id)->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'MTT001')->forceDelete();
});

it('Vehicle biz=1 aparece com session biz=1', function () {
    session(['user.business_id' => BIZ_WAGNER_VEH]);

    $v = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER_VEH,
        'plate'        => 'MTT002',
        'vehicle_type' => 'automovel',
    ]);

    session(['user.business_id' => BIZ_WAGNER_VEH]);
    $resultado = Vehicle::where('id', $v->id)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->plate)->toBe('MTT002');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'MTT002')->forceDelete();
});

it('creating event auto-popula business_id da sessão', function () {
    session(['user.business_id' => BIZ_WAGNER_VEH]);

    // Cria SEM business_id explícito — hook creating deve popular
    $v = Vehicle::create([
        'plate'        => 'MTT003',
        'vehicle_type' => 'motocicleta',
    ]);

    expect($v->business_id)->toBe(BIZ_WAGNER_VEH);
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'MTT003')->forceDelete();
});

it('Vehicle::all() respeita escopo (não vaza cross-business)', function () {
    // Insere em biz=1
    session(['user.business_id' => BIZ_WAGNER_VEH]);
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_WAGNER_VEH,
        'plate'        => 'MTT004A',
        'vehicle_type' => 'automovel',
    ]);

    // Insere em biz=99
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: inserção direta de teste
        'business_id'  => BIZ_FICTICIO_VEH,
        'plate'        => 'MTT004B',
        'vehicle_type' => 'automovel',
    ]);

    // Com session biz=1, Vehicle::where('plate', 'LIKE MTT004%') só vê o A
    session(['user.business_id' => BIZ_WAGNER_VEH]);
    $vistos = Vehicle::where('plate', 'like', 'MTT004%')->pluck('plate')->toArray();

    expect($vistos)->toContain('MTT004A');
    expect($vistos)->not->toContain('MTT004B');
})->afterEach(function () {
    Vehicle::withoutGlobalScopes()->where('plate', 'like', 'MTT004%')->forceDelete();
});
