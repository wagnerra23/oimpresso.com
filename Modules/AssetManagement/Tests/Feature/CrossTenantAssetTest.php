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
 *   1. Asset+Maintenance criados no tenant dono e no adversário separadamente
 *   2. JOIN filtrado pelo dono NÃO retorna manutenções do adversário
 *   3. JOIN filtrado pelo adversário NÃO retorna manutenções do dono
 *   4. forDropdown($business_id) respeita escopo (sanity check)
 *
 * ADR 0093: multi-tenant isolation Tier 0 IRREVOGÁVEL.
 * ADR 0358: tenant canônico de teste é o FICTÍCIO 98 (`seededTenant()`); 99 é o adversário
 * cross-tenant (`seededSupportClientTenant()`). biz=1 é a WR2 Sistemas, empresa REAL — no
 * CT 100 a base é clone de prod e não se limpa entre execuções, então fixture em biz=1 semeia
 * dado dentro do espelho da empresa de verdade. biz=4 (ROTA LIVRE) é proibido sem exceção.
 *
 * @see Modules\AssetManagement\Tests\Feature\MultiTenantIsolationTest
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

// Guard SQLite: Models AssetManagement legacy dependem do schema MySQL UltimatePOS
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_maintenances')) {
        $this->markTestSkipped('Tabelas assets/asset_maintenances ausentes — rode migrate primeiro');
    }
});

it('cross-tenant: JOIN assets-maintenance filtrado pelo dono NÃO vaza manutenção do adversário', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    // Asset + manutenção no adversário
    $assetAdversario = Asset::create([
        'business_id'    => $adversario->id,
        'name'           => 'Asset Fictício adversário',
        'asset_code'     => 'AST-CRS-X91',
        'quantity'       => 1,
        'unit_price'     => 1000.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $adversario->owner_id,
    ]);

    $maintAdversario = AssetMaintenance::create([
        'asset_id'    => $assetAdversario->id,
        'business_id' => $adversario->id,
        'status'      => 'completed',
        'details'     => 'Manutenção sigilosa do adversário',
        'created_by'  => $adversario->owner_id,
    ]);

    // Asset + manutenção no tenant dono (alvo da consulta)
    $assetDono = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Asset do tenant dono',
        'asset_code'     => 'AST-CRS-X12',
        'quantity'       => 1,
        'unit_price'     => 2000.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    AssetMaintenance::create([
        'asset_id'    => $assetDono->id,
        'business_id' => $dono->id,
        'status'      => 'in_progress',
        'details'     => 'Manutenção legítima do dono',
        'created_by'  => $dono->owner_id,
    ]);

    // Query JOIN filtrando pelo dono — não deve retornar manutenção do adversário
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', $dono->id)
        ->select('asset_maintenances.*')
        ->get();

    expect($resultado->pluck('id')->all())->not->toContain($maintAdversario->id);
    expect($resultado->pluck('details')->all())->not->toContain('Manutenção sigilosa do adversário');
})->afterEach(function () {
    foreach (['AST-CRS-X91', 'AST-CRS-X12'] as $code) {
        $asset = Asset::where('asset_code', $code)->first();
        if ($asset) {
            AssetMaintenance::where('asset_id', $asset->id)->forceDelete();
            $asset->forceDelete();
        }
    }
});

it('cross-tenant: JOIN filtrado pelo adversário NÃO vaza manutenção do dono (direção inversa)', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $assetDono = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Asset do dono Inverso',
        'asset_code'     => 'AST-CRS-X13',
        'quantity'       => 1,
        'unit_price'     => 2500.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $maintDono = AssetMaintenance::create([
        'asset_id'    => $assetDono->id,
        'business_id' => $dono->id,
        'status'      => 'completed',
        'details'     => 'Confidencial do dono',
        'created_by'  => $dono->owner_id,
    ]);

    // Atacante simulado no adversário tenta puxar manutenção do dono
    $resultado = AssetMaintenance::join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
        ->where('assets.business_id', $adversario->id)
        ->where('asset_maintenances.id', $maintDono->id)
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
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    // Asset do adversário, alocável (não deve aparecer no dropdown do dono)
    Asset::create([
        'business_id'    => $adversario->id,
        'name'           => 'Dropdown adversario (não deve vazar)',
        'asset_code'     => 'AST-CRS-X99D',
        'quantity'       => 5,
        'unit_price'     => 100.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $adversario->owner_id,
    ]);

    $assetDono = Asset::create([
        'business_id'    => $dono->id,
        'name'           => 'Dropdown Legítimo do dono',
        'asset_code'     => 'AST-CRS-X1D',
        'quantity'       => 3,
        'unit_price'     => 150.00,
        'is_allocatable' => 1,
        'purchase_type'  => 'owned',
        'created_by'     => $dono->owner_id,
    ]);

    $dropdown = Asset::forDropdown($dono->id, false, false);

    expect($dropdown['assets'])->toBeArray();
    // Não pode listar o asset do adversário
    $nomes = collect($dropdown['assets'])->values()->all();
    foreach ($nomes as $label) {
        expect($label)->not->toContain('adversario');
    }
    // Deve listar o asset legítimo
    expect($dropdown['assets'])->toHaveKey($assetDono->id);
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
