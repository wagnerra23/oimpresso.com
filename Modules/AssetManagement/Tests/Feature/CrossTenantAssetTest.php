<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Services\AssetAllocationService;

uses(Tests\TestCase::class);

/**
 * Cross-tenant isolation Tier 0 — Asset + AssetMaintenance via JOIN chain.
 *
 * Complementa MultiTenantIsolationTest.php (Wave B) reforçando o vetor JOIN:
 * mesmo quando o atacante consegue manipular o `asset_id` de outro tenant,
 * o JOIN com `assets.business_id` deve barrar o vazamento.
 *
 * Cenários:
 *   1. Asset+Maintenance criados em biz=1 e biz=99 separadamente
 *   2. JOIN filtrado por biz=1 NÃO retorna manutenções de biz=99 (mesmo se ID coincidir)
 *   3. JOIN filtrado por biz=99 NÃO retorna manutenções de biz=1
 *   4. forDropdown($business_id) respeita escopo (sanity check)
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa) em tests; usar biz=1 + biz=99.
 *
 * @see Modules\AssetManagement\Tests\Feature\MultiTenantIsolationTest
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite: Models AssetManagement legacy dependem do schema MySQL UltimatePOS
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS — ADR 0101');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_maintenances')) {
        $this->markTestSkipped('Tabelas assets/asset_maintenances ausentes — rode migrate primeiro');
    }
});

const BIZ_WAGNER_X = 1;
const BIZ_FICTICIO_X = 99;

it('cross-tenant: JOIN assets-maintenance filtrado biz=1 NÃO vaza manutenção de biz=99', function () {
    // Asset + manutenção em biz=99 (fictício "atacante")
    $assetBiz99 = Asset::create([
        'business_id'    => BIZ_FICTICIO_X,
        'name'           => 'Asset Fictício biz=99',
        'asset_code'     => 'AST-CRS-X91',
        'quantity'       => 1,
        'unit_price'     => 1000.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    $maintBiz99 = AssetMaintenance::create([
        'asset_id'         => $assetBiz99->id,
        'business_id'      => BIZ_FICTICIO_X,
        'maintenance_date' => now()->toDateString(),
        'description'      => 'Manutenção sigilosa biz=99',
        'cost'             => 500.00,
        'status'           => 'completed',
    ]);

    // Asset + manutenção em biz=1 (Wagner — tenant alvo da consulta)
    $assetBiz1 = Asset::create([
        'business_id'    => BIZ_WAGNER_X,
        'name'           => 'Asset Wagner biz=1',
        'asset_code'     => 'AST-CRS-X12',
        'quantity'       => 1,
        'unit_price'     => 2000.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    AssetMaintenance::create([
        'asset_id'         => $assetBiz1->id,
        'business_id'      => BIZ_WAGNER_X,
        'maintenance_date' => now()->toDateString(),
        'description'      => 'Manutenção legítima biz=1',
        'cost'             => 750.00,
        'status'           => 'in_progress',
    ]);

    // Query JOIN filtrando por biz=1 — não deve retornar manutenção de biz=99
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', BIZ_WAGNER_X)
        ->select('asset_maintenances.*')
        ->get();

    // Asserts: nenhuma manutenção retornada deve ser do biz=99
    expect($resultado->pluck('id')->all())->not->toContain($maintBiz99->id);
    expect($resultado->pluck('description')->all())->not->toContain('Manutenção sigilosa biz=99');
})->afterEach(function () {
    foreach (['AST-CRS-X91', 'AST-CRS-X12'] as $code) {
        $asset = Asset::where('asset_code', $code)->first();
        if ($asset) {
            AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
            $asset->forceDelete();
        }
    }
});

it('cross-tenant: JOIN filtrado biz=99 NÃO vaza manutenção de biz=1 (direção inversa)', function () {
    $assetBiz1 = Asset::create([
        'business_id'    => BIZ_WAGNER_X,
        'name'           => 'Asset Wagner Inverso',
        'asset_code'     => 'AST-CRS-X13',
        'quantity'       => 1,
        'unit_price'     => 2500.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    $maintBiz1 = AssetMaintenance::create([
        'asset_id'         => $assetBiz1->id,
        'business_id'      => BIZ_WAGNER_X,
        'maintenance_date' => now()->toDateString(),
        'description'      => 'Confidencial biz=1',
        'cost'             => 900.00,
        'status'           => 'completed',
    ]);

    // Atacante simulado em biz=99 tenta puxar manutenção de biz=1
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', BIZ_FICTICIO_X)
        ->where('asset_maintenances.id', $maintBiz1->id)
        ->select('asset_maintenances.*')
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    $asset = Asset::where('asset_code', 'AST-CRS-X13')->first();
    if ($asset) {
        AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
        $asset->forceDelete();
    }
});

it('cross-tenant: Asset::forDropdown($biz) sanity — só retorna assets do business correto', function () {
    // Asset biz=99 alocável (não deve aparecer no dropdown biz=1)
    Asset::create([
        'business_id'    => BIZ_FICTICIO_X,
        'name'           => 'Dropdown biz=99 (não deve vazar)',
        'asset_code'     => 'AST-CRS-X99D',
        'quantity'       => 5,
        'unit_price'     => 100.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    $asset1 = Asset::create([
        'business_id'    => BIZ_WAGNER_X,
        'name'           => 'Dropdown Wagner Legítimo',
        'asset_code'     => 'AST-CRS-X1D',
        'quantity'       => 3,
        'unit_price'     => 150.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
    ]);

    $dropdown = Asset::forDropdown(BIZ_WAGNER_X, false, false);

    expect($dropdown['assets'])->toBeArray();
    // Não pode listar o nome do biz=99
    $nomes = collect($dropdown['assets'])->values()->all();
    foreach ($nomes as $label) {
        expect($label)->not->toContain('biz=99');
    }
    // Deve listar o asset legítimo
    expect($dropdown['assets'])->toHaveKey($asset1->id);
})->afterEach(function () {
    foreach (['AST-CRS-X99D', 'AST-CRS-X1D'] as $code) {
        Asset::where('asset_code', $code)->forceDelete();
    }
});

/**
 * UC-ASSET-TENANT-01 — o saldo disponível de um bem é do SEU tenant.
 *
 * `AssetAllocationService::quantidadeDisponivel()` filtra o tenant na consulta externa
 * (`assets.business_id`) mas a subconsulta de revogados correlaciona só por `AR.asset_id`
 * e `AR.transaction_type`. Como `assets.id` é PK GLOBAL e `asset_transactions.asset_id`
 * é FK sem restrição de tenant, uma transação `revoke` gravada por OUTRA empresa sobre o
 * bem alheio entra na conta — e o saldo do dono muda sem que ninguém do lado dele mexa.
 *
 * O caso monta exatamente isso: o dono (biz=98, canônico) aloca 10; o adversário (biz=99)
 * grava um `revoke` de 4 apontando o `asset_id` do dono. O disponível do dono tem de
 * continuar 10 — o número dele não pode depender de linha de terceiro.
 *
 * ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL) · ADR 0358 (tenant canônico 98; 99 é o
 * adversário cross-tenant) — biz=4 (ROTA LIVRE, cliente real) é proibido aqui.
 */
const ASSET_CODE_TNT01 = 'AST-CRS-TNT01';

it('cross-tenant: revoke do adversário NÃO altera quantidadeDisponivel do tenant dono (UC-ASSET-TENANT-01)', function () {
    $dono = $this->seededTenant();                  // biz=98 — dono do bem
    $adversario = $this->seededSupportClientTenant(); // biz=99 — outra empresa

    expect($dono->id)->not->toBe($adversario->id);  // sem isso o teste seria tautológico

    $bem = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Bem do tenant dono',
        'asset_code'     => ASSET_CODE_TNT01,
        'quantity'       => 10,
        'unit_price'     => 100.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $alocacaoDoDono = AssetTransaction::create([
        'business_id'          => $dono->id,
        'asset_id'             => $bem->id,
        'transaction_type'     => 'allocate',
        'ref_no'               => 'ALOC-TNT01',
        'quantity'             => 10,
        'transaction_datetime' => now(),
        'created_by'           => $dono->owner_id,
    ]);

    $service = app(AssetAllocationService::class);

    $antes = $service->quantidadeDisponivel($alocacaoDoDono);
    expect($antes)->toBe(10);  // sanity: o cenário montou

    // A outra empresa grava um revoke sobre o bem que não é dela.
    AssetTransaction::create([
        'business_id'          => $adversario->id,
        'asset_id'             => $bem->id,
        'transaction_type'     => 'revoke',
        'ref_no'               => 'REVOKE-ADVERSARIO-TNT01',
        'quantity'             => 4,
        'transaction_datetime' => now(),
        'created_by'           => $adversario->owner_id,
    ]);

    $depois = $service->quantidadeDisponivel($alocacaoDoDono);

    expect($depois)->toBe($antes);
    expect($depois)->toBe(10);
})->afterEach(function () {
    $bem = Asset::where('asset_code', ASSET_CODE_TNT01)->first();
    if ($bem) {
        AssetTransaction::where('asset_id', $bem->id)->forceDelete();
        $bem->forceDelete();
    }
});
