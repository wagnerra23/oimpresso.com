<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\BranchCapital;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant isolation BranchCapital.
 *
 * BranchCapital registra aporte de capital social por filial. Vazamento = expõe
 * estrutura patrimonial entre tenants. Tier 0 ADR 0093.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível (ADR 0101).');
    }
    if (! Schema::hasTable('branch_capitals') || ! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Tabelas branch_capitals/chart_of_accounts missing.');
    }
});

const CROSS_BC_BIZ_WAGNER = 1;
const CROSS_BC_BIZ_FICTICIO = 99;

function setCrossBcBizSession(int $businessId): void
{
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);
}

it('cross-tenant: BranchCapital biz=1 invisível em raw biz=99', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_BC_BIZ_WAGNER,
        'name'         => 'COA pra BC CT',
        'gl_code'      => 'CT18-BC-COA',
        'account_type' => 'equity',
        'active'       => 1,
    ]);

    $bc = BranchCapital::create([ // SUPERADMIN
        'business_id'         => CROSS_BC_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'amount'              => 100000,
        'capital_type'        => 'investment',
    ]);

    $rawCount = DB::table('branch_capitals')
        ->where('business_id', CROSS_BC_BIZ_FICTICIO)
        ->where('id', $bc->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    BranchCapital::withoutGlobalScopes()
        ->whereHas('chart_of_account', fn ($q) => $q->withoutGlobalScopes()->where('gl_code', 'CT18-BC-COA'))
        ->forceDelete();
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-BC-COA')->forceDelete();
});

it('cross-tenant: BranchCapital escape valve withoutGlobalScopes funciona', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_BC_BIZ_WAGNER,
        'name'         => 'COA pra BC Scope',
        'gl_code'      => 'CT18-BC-COA-S',
        'account_type' => 'equity',
        'active'       => 1,
    ]);

    $bc = BranchCapital::create([ // SUPERADMIN
        'business_id'         => CROSS_BC_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'amount'              => 250000,
        'capital_type'        => 'investment',
    ]);

    setCrossBcBizSession(CROSS_BC_BIZ_FICTICIO);
    expect(BranchCapital::where('id', $bc->id)->get())->toHaveCount(0);
    expect(BranchCapital::withoutGlobalScopes()->where('id', $bc->id)->get())->toHaveCount(1);
})->afterEach(function () {
    BranchCapital::withoutGlobalScopes()
        ->whereHas('chart_of_account', fn ($q) => $q->withoutGlobalScopes()->where('gl_code', 'CT18-BC-COA-S'))
        ->forceDelete();
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-BC-COA-S')->forceDelete();
});
