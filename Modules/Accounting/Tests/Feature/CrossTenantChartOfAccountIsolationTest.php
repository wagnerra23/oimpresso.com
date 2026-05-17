<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant isolation focada em ChartOfAccount (COA).
 *
 * Cross-tenant Tier 0 ADR 0093 — COA é root da contabilidade (Asset/Liability/Equity/
 * Revenue/Expense). Vazamento entre tenants = vazamento de DRE/Balanço entre clientes.
 *
 * Usa constantes BIZ_FICTICIO / BIZ_WAGNER (convenção cross-tenant ADR 0101).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS exigido (ADR 0101).');
    }
    if (! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Tabela chart_of_accounts missing.');
    }
});

const CROSS_COA_BIZ_WAGNER = 1;
const CROSS_COA_BIZ_FICTICIO = 99;

function setCrossCoaBizSession(int $businessId): void
{
    session([
        'business.id'      => $businessId,
        'user.business_id' => $businessId,
    ]);
}

it('cross-tenant: COA biz=1 não vaza pra biz=99 via raw query (defesa Tier 0)', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN: seed isolation
        'business_id'  => CROSS_COA_BIZ_WAGNER,
        'name'         => 'COA CT Wave 18 Asset',
        'gl_code'      => 'CT18-COA-A001',
        'account_type' => 'asset',
        'active'       => 1,
    ]);

    $rawCount = DB::table('chart_of_accounts')
        ->where('business_id', CROSS_COA_BIZ_FICTICIO)
        ->where('id', $coa->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-COA-A001')->forceDelete();
});

it('cross-tenant: COA via scope forBusiness não retorna biz=1 com session biz=99', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_COA_BIZ_WAGNER,
        'name'         => 'COA CT Wave 18 Liability',
        'gl_code'      => 'CT18-COA-L002',
        'account_type' => 'liability',
        'active'       => 1,
    ]);

    setCrossCoaBizSession(CROSS_COA_BIZ_FICTICIO);

    $scoped = ChartOfAccount::forBusiness()
        ->where('id', $coa->id)
        ->get();

    expect($scoped)->toHaveCount(0);
})->afterEach(function () {
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-COA-L002')->forceDelete();
});

it('cross-tenant: COA withoutGlobalScopes(ScopeByBusiness) volta a ver biz=1', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_COA_BIZ_WAGNER,
        'name'         => 'COA CT Wave 18 Equity',
        'gl_code'      => 'CT18-COA-E003',
        'account_type' => 'equity',
        'active'       => 1,
    ]);

    setCrossCoaBizSession(CROSS_COA_BIZ_FICTICIO);

    // Scope ativo: NÃO vê
    $scoped = ChartOfAccount::where('id', $coa->id)->get();
    expect($scoped)->toHaveCount(0);

    // Escape valve: vê
    $unscoped = ChartOfAccount::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $coa->id)
        ->get();
    expect($unscoped)->toHaveCount(1);
})->afterEach(function () {
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-COA-E003')->forceDelete();
});
