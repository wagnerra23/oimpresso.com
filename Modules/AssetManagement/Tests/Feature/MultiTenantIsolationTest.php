<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 dos Models AssetManagement.
 *
 * ATENÇÃO: Modules/AssetManagement é módulo LEGACY UltimatePOS — NÃO usa BusinessScope global.
 * Isolamento é feito MANUALMENTE nos Controllers via `where('business_id', $business_id)`.
 * Este test valida que dados de biz=1 não vazam para biz=99 quando filtros são aplicados.
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa) em tests; usar biz=1 (Wagner WR2) + biz=99 fictício.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite: Models AssetManagement legacy dependem do schema MySQL UltimatePOS completo
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS — ADR 0101');
    }
    if (! Schema::hasTable('assets')) {
        $this->markTestSkipped('assets table missing — rode Modules/AssetManagement migrate primeiro');
    }
    if (! Schema::hasTable('asset_maintenances')) {
        $this->markTestSkipped('asset_maintenances table missing — rode Modules/AssetManagement migrate primeiro');
    }
});

// IDs canônicos — biz=1 (Wagner WR2) e biz=99 (fictício)
const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// Asset — isolamento via filtro manual where('business_id', ...)
// ------------------------------------------------------------------

it('Asset biz=1 não aparece em query filtrada por biz=99', function () {
    // Criar asset em biz=1
    $asset = Asset::create([
        'business_id'    => BIZ_WAGNER,
        'name'           => 'Notebook Teste Isolamento WR2',
        'asset_code'     => 'AST-TST-9991',
        'quantity'       => 1,
        'unit_price'     => 3500.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    // Query filtrando por biz=99 — NÃO deve trazer o asset criado em biz=1
    $resultado = Asset::where('business_id', BIZ_FICTICIO)
        ->where('id', $asset->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Asset::where('business_id', BIZ_WAGNER)
        ->where('asset_code', 'AST-TST-9991')
        ->forceDelete();
});

it('Asset biz=1 aparece em query filtrada por biz=1', function () {
    $asset = Asset::create([
        'business_id'    => BIZ_WAGNER,
        'name'           => 'Impressora Teste WR2',
        'asset_code'     => 'AST-TST-9992',
        'quantity'       => 2,
        'unit_price'     => 1200.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    $resultado = Asset::where('business_id', BIZ_WAGNER)
        ->where('id', $asset->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Impressora Teste WR2');
    expect((int) $resultado->first()->business_id)->toBe(BIZ_WAGNER);
})->afterEach(function () {
    Asset::where('business_id', BIZ_WAGNER)
        ->where('asset_code', 'AST-TST-9992')
        ->forceDelete();
});

// ------------------------------------------------------------------
// AssetMaintenance — isolamento via asset.business_id (relacionamento)
// ------------------------------------------------------------------

it('AssetMaintenance biz=1 não aparece em join filtrado por biz=99', function () {
    // Criar asset pai em biz=1
    $asset = Asset::create([
        'business_id'    => BIZ_WAGNER,
        'name'           => 'Servidor Teste Manutencao',
        'asset_code'     => 'AST-TST-9993',
        'quantity'       => 1,
        'unit_price'     => 8000.00,
        'is_allocatable' => 0,
        'purchase_type'  => 'owned',
    ]);

    // Criar manutenção vinculada
    $maintenance = AssetMaintenance::create([
        'asset_id'         => $asset->id,
        'business_id'      => BIZ_WAGNER,
        'maintenance_date' => now()->toDateString(),
        'completion_date'  => now()->addDay()->toDateString(),
        'description'      => 'Manutenção teste isolamento',
        'cost'             => 200.00,
        'status'           => 'completed',
    ]);

    // Query JOIN filtrando por biz=99 NÃO deve retornar manutenção do biz=1
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', BIZ_FICTICIO)
        ->where('asset_maintenances.id', $maintenance->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    $asset = Asset::where('asset_code', 'AST-TST-9993')->first();
    if ($asset) {
        AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
        $asset->forceDelete();
    }
});

it('AssetMaintenance biz=1 aparece em join filtrado por biz=1', function () {
    $asset = Asset::create([
        'business_id'    => BIZ_WAGNER,
        'name'           => 'Servidor Teste Manutencao 2',
        'asset_code'     => 'AST-TST-9994',
        'quantity'       => 1,
        'unit_price'     => 8500.00,
        'is_allocatable' => 0,
        'purchase_type'  => 'owned',
    ]);

    $maintenance = AssetMaintenance::create([
        'asset_id'         => $asset->id,
        'business_id'      => BIZ_WAGNER,
        'maintenance_date' => now()->toDateString(),
        'description'      => 'Manutenção teste positivo',
        'cost'             => 350.00,
        'status'           => 'in_progress',
    ]);

    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', BIZ_WAGNER)
        ->where('asset_maintenances.id', $maintenance->id)
        ->select('asset_maintenances.*')
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->description)->toBe('Manutenção teste positivo');
})->afterEach(function () {
    $asset = Asset::where('asset_code', 'AST-TST-9994')->first();
    if ($asset) {
        AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
        $asset->forceDelete();
    }
});
