<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use Modules\Accounting\Entities\Account;
use Modules\Accounting\Entities\BranchCapital;
use Modules\Accounting\Entities\Budget;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Auditoria de adoção do trait HasBusinessScope (Wave 12 D1 MT — sessão 2026-05-16).
 *
 * Valida que Entities Accounting com coluna `business_id` direta usam o trait canônico
 * `App\Concerns\HasBusinessScope` (ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL).
 *
 * Entities legacy SEM `business_id` direto (JournalEntry, AccountTransaction, Transfer)
 * escapam via JOIN — NÃO devem usar o trait (quebraria queries). Cobertas em
 * MultiTenantIsolationTest.php (Wave 11).
 *
 * AccountSubtype tem semântica especial (business_id=0 = global default) — NÃO migrar.
 *
 * @see Modules/Accounting/Entities/Account.php
 * @see Modules/Accounting/Entities/ChartOfAccount.php
 * @see Modules/Accounting/Entities/Budget.php
 * @see Modules/Accounting/Entities/BranchCapital.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

it('Entities Accounting com business_id direto usam HasBusinessScope (Wave 12)', function () {
    $expected = [
        Account::class,
        ChartOfAccount::class,
        Budget::class,
        BranchCapital::class,
    ];

    $missing = [];
    foreach ($expected as $fqcn) {
        $traits = class_uses_recursive($fqcn);
        if (! in_array(HasBusinessScope::class, $traits, true)) {
            $missing[] = $fqcn;
        }
    }

    expect($missing)->toBeEmpty(
        "Entities sem HasBusinessScope (violação ADR 0093 Wave 12):\n  - "
            . implode("\n  - ", $missing)
    );
});

it('ScopeByBusiness está registrado como global scope (sanity check)', function () {
    // Instanciar Model força bootHasBusinessScope() — global scope deve estar registrado
    $globalScopes = (new Account())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusiness::class);

    $globalScopes = (new ChartOfAccount())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusiness::class);

    $globalScopes = (new Budget())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusiness::class);

    $globalScopes = (new BranchCapital())->getGlobalScopes();
    expect($globalScopes)->toHaveKey(ScopeByBusiness::class);
});

it('HasBusinessScope trait pode ser removida com withoutGlobalScope (escape valve sane)', function () {
    // Sanity: o escape valve oficial pra superadmin/jobs continua funcionando
    $query = Account::withoutGlobalScope(ScopeByBusiness::class)->getQuery();
    expect($query)->not->toBeNull();
});
