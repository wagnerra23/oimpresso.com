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
 * Este test valida que dados do tenant dono não vazam para o adversário quando os filtros
 * são aplicados.
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0358: tenant canônico de teste é o FICTÍCIO 98 (`seededTenant()`); 99 é o adversário
 * cross-tenant (`seededSupportClientTenant()`). biz=1 é a WR2 Sistemas, empresa REAL — no
 * CT 100 a base é clone de prod e não se limpa entre execuções, então fixture em biz=1 semeia
 * dado dentro do espelho da empresa de verdade. biz=4 (ROTA LIVRE) é proibido sem exceção.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

// Guard SQLite: Models AssetManagement legacy dependem do schema MySQL UltimatePOS completo
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets')) {
        $this->markTestSkipped('assets table missing — rode Modules/AssetManagement migrate primeiro');
    }
    if (! Schema::hasTable('asset_maintenances')) {
        $this->markTestSkipped('asset_maintenances table missing — rode Modules/AssetManagement migrate primeiro');
    }
});

// ------------------------------------------------------------------
// Asset — isolamento via filtro manual where('business_id', ...)
// ------------------------------------------------------------------

it('Asset do tenant dono não aparece em query filtrada pelo adversário', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $asset = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Notebook Teste Isolamento',
        'asset_code'     => 'AST-TST-9991',
        'quantity'       => 1,
        'unit_price'     => 3500.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    // Query filtrando pelo adversário — NÃO deve trazer o asset do tenant dono
    $resultado = Asset::where('business_id', $adversario->id)
        ->where('id', $asset->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Asset::where('asset_code', 'AST-TST-9991')->forceDelete();
});

it('Asset do tenant dono aparece em query filtrada por ele mesmo', function () {
    $dono = $this->seededTenant();

    $asset = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Impressora Teste',
        'asset_code'     => 'AST-TST-9992',
        'quantity'       => 2,
        'unit_price'     => 1200.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $resultado = Asset::where('business_id', $dono->id)
        ->where('id', $asset->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Impressora Teste');
    expect((int) $resultado->first()->business_id)->toBe((int) $dono->id);
})->afterEach(function () {
    Asset::where('asset_code', 'AST-TST-9992')->forceDelete();
});

// ------------------------------------------------------------------
// AssetMaintenance — isolamento via asset.business_id (relacionamento)
// ------------------------------------------------------------------

it('AssetMaintenance do dono não aparece em join filtrado pelo adversário', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $asset = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Servidor Teste Manutencao',
        'asset_code'     => 'AST-TST-9993',
        'quantity'       => 1,
        'unit_price'     => 8000.00,
        'is_allocatable' => 0,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $maintenance = AssetMaintenance::create([
        'asset_id'    => $asset->id,
        'business_id' => $dono->id,
        'status'      => 'completed',
        'details'     => 'Manutenção teste isolamento',
        'created_by'  => $dono->owner_id,
    ]);

    // Query JOIN filtrando pelo adversário NÃO deve retornar manutenção do dono
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', $adversario->id)
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

it('AssetMaintenance do dono aparece em join filtrado por ele mesmo', function () {
    $dono = $this->seededTenant();

    $asset = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Servidor Teste Manutencao 2',
        'asset_code'     => 'AST-TST-9994',
        'quantity'       => 1,
        'unit_price'     => 8500.00,
        'is_allocatable' => 0,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $maintenance = AssetMaintenance::create([
        'asset_id'    => $asset->id,
        'business_id' => $dono->id,
        'status'      => 'in_progress',
        'details'     => 'Manutenção teste positivo',
        'created_by'  => $dono->owner_id,
    ]);

    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', $dono->id)
        ->where('asset_maintenances.id', $maintenance->id)
        ->select('asset_maintenances.*')
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->details)->toBe('Manutenção teste positivo');
})->afterEach(function () {
    $asset = Asset::where('asset_code', 'AST-TST-9994')->first();
    if ($asset) {
        AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
        $asset->forceDelete();
    }
});
