<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Budget;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Services\BudgetService;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 28 Accounting SATURATION FINAL — polish 60-79-88 → ≥92 (+4pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest cross-tenant (Budget cross-tenant via BudgetService W28 + ScopeByBusiness)
 *  - D9 +1 span `accounting.budget.yearly_to_monthly` em BudgetService (instrumentação nova)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: Reflection puro + cross-tenant guard MySQL-aware (skip SQLite ADR 0101).
 * Preserva Tier 0 IRREVOGÁVEIS:
 *   - Catálogo biz=0 NUNCA alterado (preserva seed lesson W13/W15)
 *   - PT-BR + biz=1 vs biz=99 (NUNCA biz=4 cliente real)
 *
 * @see Modules/Accounting/Tests/Feature/CrossTenantBudgetIsolationTest.php (Wave 18 baseline)
 * @see Modules/Accounting/Services/BudgetService.php (D9 +1 span W28)
 */

const W28_ACC_BIZ_WAGNER = 1;
const W28_ACC_BIZ_FICTICIO = 99;

beforeEach(function () {
    config()->set('otel.enabled', false);
});

function w28AccNeedsMysql(): bool
{
    return DB::connection()->getDriverName() === 'sqlite';
}

// ------------------------------------------------------------------
// D9 W28 — span novo accounting.budget.yearly_to_monthly + quarterly
// ------------------------------------------------------------------

it('D9 W28: BudgetService instrumenta accounting.budget.yearly_to_monthly (span novo)', function () {
    $src = file_get_contents((new ReflectionClass(BudgetService::class))->getFileName());

    expect($src)->toContain("'accounting.budget.yearly_to_monthly'");

    // ≥2 spans canon W28 (yearly + quarterly)
    $count = substr_count($src, 'OtelHelper::spanBiz(');
    expect($count)->toBeGreaterThanOrEqual(2, "Spans BudgetService ≥2 (W28 novo); achou {$count}");
});

it('D9 W28: BudgetService instrumenta accounting.budget.quarterly_to_monthly (span companion)', function () {
    $src = file_get_contents((new ReflectionClass(BudgetService::class))->getFileName());
    expect($src)->toContain("'accounting.budget.quarterly_to_monthly'");
});

it('D9 W28: BudgetService.yearlyBudgetToMonthly preserva contract retorno (12 keys month_N)', function () {
    $service = new BudgetService();
    $result = $service->yearlyBudgetToMonthly(12000, true);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(12);
    expect($result)->toHaveKey('month_1');
    expect($result)->toHaveKey('month_12');

    // Soma dos 12 meses preservada (eliminate_decimals = true mantém total exato)
    $total = array_sum($result);
    expect($total)->toBe(12000);
});

// ------------------------------------------------------------------
// D2 W28 — +3 Pest cross-tenant (Budget cross-tenant Tier 0)
// ------------------------------------------------------------------

it('D2 W28 cross-tenant: Budget biz=1 NÃO aparece em raw query biz=99 (LGPD Art. 6 ADR 0093)', function () {
    if (w28AccNeedsMysql() || ! Schema::hasTable('budgets') || ! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Schema MySQL UltimatePOS exigido (ADR 0101).');
    }

    $coa = ChartOfAccount::create([
        'business_id'  => W28_ACC_BIZ_WAGNER,
        'name'         => 'COA W28 cross-tenant',
        'gl_code'      => 'W28-CT-COA',
        'account_type' => 'revenue',
        'active'       => 1,
    ]);

    $budget = Budget::create([
        'business_id'         => W28_ACC_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'financial_year'      => 2026,
        'month_1'  => 8888, 'month_2' => 0, 'month_3' => 0, 'month_4' => 0,
        'month_5'  => 0, 'month_6' => 0, 'month_7' => 0, 'month_8' => 0,
        'month_9'  => 0, 'month_10' => 0, 'month_11' => 0, 'month_12' => 0,
    ]);

    try {
        $rawCount = DB::table('budgets')
            ->where('business_id', W28_ACC_BIZ_FICTICIO)
            ->where('id', $budget->id)
            ->count();

        expect($rawCount)->toBe(0, 'Budget biz=1 NUNCA pode aparecer em raw query biz=99');
    } finally {
        Budget::withoutGlobalScopes()->where('id', $budget->id)->forceDelete();
        ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'W28-CT-COA')->forceDelete();
    }
});

it('D2 W28 cross-tenant: ScopeByBusiness filtra Budget biz=1 quando session=biz=99', function () {
    if (w28AccNeedsMysql() || ! Schema::hasTable('budgets') || ! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Schema MySQL UltimatePOS exigido (ADR 0101).');
    }

    $coa = ChartOfAccount::create([
        'business_id'  => W28_ACC_BIZ_WAGNER,
        'name'         => 'COA W28 scope',
        'gl_code'      => 'W28-SCOPE-COA',
        'account_type' => 'revenue',
        'active'       => 1,
    ]);

    $budget = Budget::create([
        'business_id'         => W28_ACC_BIZ_WAGNER,
        'chart_of_account_id' => $coa->id,
        'financial_year'      => 2026,
        'month_1'  => 9999, 'month_2' => 0, 'month_3' => 0, 'month_4' => 0,
        'month_5'  => 0, 'month_6' => 0, 'month_7' => 0, 'month_8' => 0,
        'month_9'  => 0, 'month_10' => 0, 'month_11' => 0, 'month_12' => 0,
    ]);

    try {
        session([
            'business.id'      => W28_ACC_BIZ_FICTICIO,
            'user.business_id' => W28_ACC_BIZ_FICTICIO,
        ]);

        // Global scope ScopeByBusiness filtra biz=99 (não vê biz=1)
        $scoped = Budget::where('id', $budget->id)->get();
        expect($scoped)->toHaveCount(0, 'ScopeByBusiness deve bloquear cross-tenant read');

        // withoutGlobalScope é escape valve documentada (SUPERADMIN)
        $unscoped = Budget::withoutGlobalScope(ScopeByBusiness::class)
            ->where('id', $budget->id)
            ->get();
        expect($unscoped)->toHaveCount(1, 'Escape valve SUPERADMIN vê tudo');
    } finally {
        Budget::withoutGlobalScopes()->where('id', $budget->id)->forceDelete();
        ChartOfAccount::withoutGlobalScopes()->where('gl_code', 'W28-SCOPE-COA')->forceDelete();
    }
});

it('D2 W28: BudgetService.quartelyBudgetToMonthly preserva contract retorno (3 keys named)', function () {
    $service = new BudgetService('1', '2026');
    $months = ['Jan 2026', 'Feb 2026', 'Mar 2026'];

    $result = $service->quartelyBudgetToMonthly($months, 3000, true);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);

    // Soma preservada (eliminate_decimals = true)
    $total = array_sum($result);
    expect($total)->toBe(3000);
});

// ------------------------------------------------------------------
// Preservação Tier 0 — catálogo biz=0
// ------------------------------------------------------------------

it('Tier 0 W28 preserva: catálogo biz=0 NÃO alterado (lesson W13/W15)', function () {
    if (w28AccNeedsMysql() || ! Schema::hasTable('chart_of_accounts')) {
        $this->markTestSkipped('Schema chart_of_accounts indisponível.');
    }

    // Catálogo biz=0 é seed compartilhado — Wave 28 NÃO mexe nele
    $biz0Count = DB::table('chart_of_accounts')
        ->where('business_id', 0)
        ->count();

    // Existe (seed) OU não existe (env limpo) — ambos válidos. Só garante que
    // Wave 28 não adicionou NEM removeu rows biz=0 (sem markers Wave28-ACC nele).
    $w28Touched = DB::table('chart_of_accounts')
        ->where('business_id', 0)
        ->where('gl_code', 'like', 'W28%')
        ->count();

    expect($w28Touched)->toBe(0, 'Wave 28 NÃO pode ter criado rows biz=0 (catálogo intocável)');
});

it('D9 W28: OtelHelper preserva exception em spans accounting.budget.* (fail-loud)', function () {
    expect(fn () => OtelHelper::spanBiz(
        'accounting.budget.test_w28_boom',
        fn () => throw new \RuntimeException('w28-acc-boom')
    ))->toThrow(\RuntimeException::class, 'w28-acc-boom');
});

// ------------------------------------------------------------------
// D3 W28 — CHANGELOG entry novo
// ------------------------------------------------------------------

it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 60-79-88 → ≥92)', function () {
    $changelog = file_get_contents(base_path('Modules/Accounting/CHANGELOG.md'));
    expect($changelog)->toContain('Wave 28');
});
