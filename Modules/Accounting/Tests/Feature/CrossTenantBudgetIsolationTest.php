<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Budget;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant isolation Budget.
 *
 * Budget projeta receita/despesa mensal. Vazamento = expõe metas financeiras
 * de cliente A para cliente B (LGPD Art. 6 + ADR 0093 multi-tenant Tier 0).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS exigido (ADR 0101).');
    }
    if (! Schema::hasTable('budgets') || ! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Tabelas budgets/chart_of_accounts missing.');
    }
});

const CROSS_BUD_BIZ_WAGNER = 1;
const CROSS_BUD_BIZ_FICTICIO = 99;

function setCrossBudBizSession(int $businessId): void
{
    session([
        'business.id'      => $businessId,
        'user.business_id' => $businessId,
    ]);
}

it('cross-tenant: Budget biz=1 não aparece em raw query biz=99 (isolation)', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_BUD_BIZ_WAGNER,
        'name'         => 'COA pra Budget CT',
        'gl_code'      => 'CT18-BUD-COA',
        'account_type' => 'revenue',
        'active'       => 1,
    ]);

    $budget = Budget::create([ // SUPERADMIN
        'business_id'         => CROSS_BUD_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'financial_year'      => 2026,
        'month_1'  => 5000, 'month_2'  => 0, 'month_3'  => 0,
        'month_4'  => 0, 'month_5'  => 0, 'month_6'  => 0,
        'month_7'  => 0, 'month_8'  => 0, 'month_9'  => 0,
        'month_10' => 0, 'month_11' => 0, 'month_12' => 0,
    ]);

    $rawCount = DB::table('budgets')
        ->where('business_id', CROSS_BUD_BIZ_FICTICIO)
        ->where('id', $budget->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    Budget::withoutGlobalScopes()
        ->whereHas('chart_of_account', fn ($q) => $q->withoutGlobalScopes()->where('gl_code', 'CT18-BUD-COA'))
        ->forceDelete();
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-BUD-COA')->forceDelete();
});

it('cross-tenant: Budget global scope filtra biz=1 quando session biz=99', function () {
    $coa = ChartOfAccount::create([ // SUPERADMIN
        'business_id'  => CROSS_BUD_BIZ_WAGNER,
        'name'         => 'COA pra Budget Scope',
        'gl_code'      => 'CT18-BUD-COA-S',
        'account_type' => 'revenue',
        'active'       => 1,
    ]);

    $budget = Budget::create([ // SUPERADMIN
        'business_id'         => CROSS_BUD_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'financial_year'      => 2026,
        'month_1'  => 7777, 'month_2' => 0, 'month_3' => 0, 'month_4' => 0,
        'month_5'  => 0, 'month_6' => 0, 'month_7' => 0, 'month_8' => 0,
        'month_9'  => 0, 'month_10' => 0, 'month_11' => 0, 'month_12' => 0,
    ]);

    setCrossBudBizSession(CROSS_BUD_BIZ_FICTICIO);
    // Global scope HasBusinessScope (ScopeByBusiness) filtra
    $scoped = Budget::where('id', $budget->id)->get();
    expect($scoped)->toHaveCount(0);

    // Escape valve volta a ver
    $unscoped = Budget::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $budget->id)->get();
    expect($unscoped)->toHaveCount(1);
})->afterEach(function () {
    Budget::withoutGlobalScopes()
        ->whereHas('chart_of_account', fn ($q) => $q->withoutGlobalScopes()->where('gl_code', 'CT18-BUD-COA-S'))
        ->forceDelete();
    ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'CT18-BUD-COA-S')->forceDelete();
});
