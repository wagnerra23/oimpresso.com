<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
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
 * Auditoria de adoção do trait HasBusinessScope (Wave 12+13 D1 MT — sessão 2026-05-16).
 *
 * Valida que Entities Accounting com coluna `business_id` direta usam o trait canônico
 * `App\Concerns\HasBusinessScope` (ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL).
 *
 * Wave 12 (4 Entities): Account, ChartOfAccount, Budget, BranchCapital.
 * Wave 13 (13 Entities): Brands, Category, ContactRestriction, CustomerGroup,
 *   InvoiceLayout, InvoiceScheme, NotificationTemplate, Printer, SellingPriceGroup,
 *   TaxRate, TypesOfService, Unit, Warranty.
 *
 * Entities legacy SEM `business_id` direto (JournalEntry, AccountTransaction, Transfer)
 * escapam via JOIN — NÃO devem usar o trait (quebraria queries). Cobertas em
 * MultiTenantIsolationTest.php (Wave 11).
 *
 * Entities extending core App namespace (User, Contact, BusinessLocation, Transaction)
 * herdam global scope do parent — NÃO duplicar no child.
 *
 * Entities com semântica `business_id=0 = global default` (AccountSubtype, AccountDetailType,
 * PaymentType) — NÃO migrar (quebraria fetch de defaults plataforma-wide).
 *
 * Media — polymorphic (morphTo) com business_id mas usada cross-tenant via core uploads.
 *   Skip seguro pra Wave 13.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('Entities Accounting Wave 12 + Wave 13 + Wave 18 RETRY com business_id direto usam HasBusinessScope', function () {
    $expected = [
        // Wave 12 (sessão anterior 2026-05-16)
        Account::class,
        ChartOfAccount::class,
        Budget::class,
        BranchCapital::class,
        // Wave 13 (sessão anterior 2026-05-16)
        Brands::class,
        Category::class,
        ContactRestriction::class,
        CustomerGroup::class,
        InvoiceLayout::class,
        InvoiceScheme::class,
        NotificationTemplate::class,
        Printer::class,
        SellingPriceGroup::class,
        TaxRate::class,
        TypesOfService::class,
        Unit::class,
        Warranty::class,
        // Wave 18 RETRY (saturação D1 MT — 2026-05-16)
        ExpenseCategory::class,
        Discount::class,
        CashRegister::class,
        DashboardConfiguration::class,
    ];

    $missing = [];
    foreach ($expected as $fqcn) {
        $traits = class_uses_recursive($fqcn);
        if (! in_array(HasBusinessScope::class, $traits, true)) {
            $missing[] = $fqcn;
        }
    }

    expect($missing)->toBeEmpty(
        "Entities sem HasBusinessScope (violação ADR 0093 Wave 12+13):\n  - "
            . implode("\n  - ", $missing)
    );
});

it('ScopeByBusiness está registrado como global scope (sanity check Wave 12+13)', function () {
    // Instanciar Model força bootHasBusinessScope() — global scope deve estar registrado
    $models = [
        // Wave 12
        Account::class, ChartOfAccount::class, Budget::class, BranchCapital::class,
        // Wave 13
        Brands::class, Category::class, ContactRestriction::class, CustomerGroup::class,
        InvoiceLayout::class, InvoiceScheme::class, NotificationTemplate::class,
        Printer::class, SellingPriceGroup::class, TaxRate::class, TypesOfService::class,
        Unit::class, Warranty::class,
        // Wave 18 RETRY
        ExpenseCategory::class, Discount::class, CashRegister::class, DashboardConfiguration::class,
    ];

    $missing = [];
    foreach ($models as $fqcn) {
        $globalScopes = (new $fqcn())->getGlobalScopes();
        if (! array_key_exists(ScopeByBusiness::class, $globalScopes)) {
            $missing[] = $fqcn;
        }
    }

    expect($missing)->toBeEmpty(
        "Models sem ScopeByBusiness registrado:\n  - " . implode("\n  - ", $missing)
    );
});

it('HasBusinessScope trait pode ser removida com withoutGlobalScope (escape valve sane)', function () {
    // Sanity: o escape valve oficial pra superadmin/jobs continua funcionando
    $query = Account::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull();

    // Sanity Wave 13 — escape valve idêntico nas novas Entities
    $query = TaxRate::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull();
});
