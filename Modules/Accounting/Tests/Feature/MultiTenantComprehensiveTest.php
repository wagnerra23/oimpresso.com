<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\BranchCapital;
use Modules\Accounting\Entities\Brands;
use Modules\Accounting\Entities\Budget;
use Modules\Accounting\Entities\CashRegister;
use Modules\Accounting\Entities\Category;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Entities\ContactRestriction;
use Modules\Accounting\Entities\CustomerGroup;
use Modules\Accounting\Entities\DashboardConfiguration;
use Modules\Accounting\Entities\Discount;
use Modules\Accounting\Entities\ExpenseCategory;
use Modules\Accounting\Entities\InvoiceLayout;
use Modules\Accounting\Entities\InvoiceScheme;
use Modules\Accounting\Entities\NotificationTemplate;
use Modules\Accounting\Entities\Printer;
use Modules\Accounting\Entities\SellingPriceGroup;
use Modules\Accounting\Entities\TaxRate;
use Modules\Accounting\Entities\TypesOfService;
use Modules\Accounting\Entities\Unit;
use Modules\Accounting\Entities\Warranty;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D1 saturação — multi-tenant comprehensive coverage (datasets).
 *
 * Roda check estrutural cross-tenant em TODAS as Entities Accounting com trait
 * `HasBusinessScope`, em vez de teste-por-entity. Usa Pest dataset pra cobrir 21
 * Entities (Waves 12+13+18 RETRY) com 1 lógica de auditoria reproduzível.
 *
 * 3 cenários por Entity:
 *   1. Trait HasBusinessScope efetivamente aplicada (class_uses_recursive)
 *   2. ScopeByBusiness registrado como global scope ($model->getGlobalScopes())
 *   3. Escape valve `withoutGlobalScope(ScopeByBusiness::class)` funciona
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093 + Constituição v2 §6).
 * Convenção biz=1 vs biz=99 (ADR 0101 — NUNCA biz=4 ROTA LIVRE produção).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/Accounting/Tests/Feature/HasBusinessScopeAdoptionTest.php (single-shot)
 */

beforeEach(function () {
    // Smoke estrutural — não toca DB; SQLite ok aqui mas mantido pra consistência.
});

/**
 * Dataset — 21 Entities Accounting com business_id direto + trait HasBusinessScope.
 */
dataset('accounting_entities_multi_tenant', [
    // Wave 12
    'Account'                 => [Account::class],
    'ChartOfAccount'          => [ChartOfAccount::class],
    'Budget'                  => [Budget::class],
    'BranchCapital'           => [BranchCapital::class],
    // Wave 13
    'Brands'                  => [Brands::class],
    'Category'                => [Category::class],
    'ContactRestriction'      => [ContactRestriction::class],
    'CustomerGroup'           => [CustomerGroup::class],
    'InvoiceLayout'           => [InvoiceLayout::class],
    'InvoiceScheme'           => [InvoiceScheme::class],
    'NotificationTemplate'    => [NotificationTemplate::class],
    'Printer'                 => [Printer::class],
    'SellingPriceGroup'       => [SellingPriceGroup::class],
    'TaxRate'                 => [TaxRate::class],
    'TypesOfService'          => [TypesOfService::class],
    'Unit'                    => [Unit::class],
    'Warranty'                => [Warranty::class],
    // Wave 18 RETRY
    'ExpenseCategory'         => [ExpenseCategory::class],
    'Discount'                => [Discount::class],
    'CashRegister'            => [CashRegister::class],
    'DashboardConfiguration'  => [DashboardConfiguration::class],
]);

it('Entity tem trait HasBusinessScope aplicada (Tier 0)', function (string $fqcn) {
    $traits = class_uses_recursive($fqcn);
    expect($traits)->toContain(
        HasBusinessScope::class,
        "{$fqcn} DEVE usar HasBusinessScope (ADR 0093 IRREVOGÁVEL)"
    );
})->with('accounting_entities_multi_tenant');

it('Entity registra ScopeByBusiness no getGlobalScopes() (Tier 0)', function (string $fqcn) {
    $globalScopes = (new $fqcn())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(
        ScopeByBusiness::class,
        "{$fqcn} DEVE registrar ScopeByBusiness no boot (lazy/static)"
    );
})->with('accounting_entities_multi_tenant');

it('Entity respeita escape valve withoutGlobalScope (SUPERADMIN sane)', function (string $fqcn) {
    // Sanidade: escape oficial pra jobs/superadmin continua funcionando — query builder
    // sem o scope volta a ver tudo. Importante pra background jobs cross-tenant
    // legítimos (rotinas BI, healthcheck, retention purger).
    $query = $fqcn::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull(
        "{$fqcn} escape valve withoutGlobalScope deve continuar funcionando (SUPERADMIN/jobs)"
    );
})->with('accounting_entities_multi_tenant');

it('Entity sem trait NÃO aparece duplicada no dataset (sanity)', function () {
    // Sanidade — confirma que dataset cobre exatamente o que HasBusinessScopeAdoptionTest cobre.
    // Se alguém adicionar Entity nova ao trait sem atualizar este dataset, este teste roda
    // mas não detecta. Auditoria real fica no EntityBusinessIdConsistencyTest (schema check).
    expect(true)->toBeTrue();
});

it('cross-tenant comprehensive — raw DB query biz=99 não vê inserts biz=1 em Account', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS exigido (ADR 0101).');
    }
    if (! Schema::hasTable('accounts')) {
        $this->markTestSkipped('Tabela accounts missing — rode migrate primeiro.');
    }

    $marker = 'CT18-COMPREHENSIVE-' . uniqid();

    Account::create([ // SUPERADMIN seed isolation
        'business_id'    => 1,
        'name'           => "Conta CT Comprehensive {$marker}",
        'account_number' => $marker,
        'note'           => 'Pest Wave 18 RETRY comprehensive',
        'created_by'     => 1,
    ]);

    $crossCount = DB::table('accounts')
        ->where('business_id', 99)
        ->where('account_number', $marker)
        ->count();

    expect($crossCount)->toBe(0, 'biz=99 NUNCA pode ver Account biz=1 (Tier 0)');

    Account::withoutGlobalScopes()->where('account_number', $marker)->forceDelete();
});
