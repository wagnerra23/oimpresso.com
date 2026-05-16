<?php

declare(strict_types=1);

use Modules\Accounting\Entities\AccountTransaction;

uses(Tests\TestCase::class);

/**
 * Reflection-only — verifica que Entities Tier 0 do Accounting declaram
 * trait HasBusinessScope (business_id direto) ou BelongsToBusinessViaParent
 * (child de outra entity tenant) por ADR 0093.
 *
 * Tests NÃO requerem DB — só reflection sobre class_uses_recursive e
 * propriedades estáticas. Rodam em SQLite ou MySQL indiferentemente.
 *
 * Wave 15 D1 MT rescue (2026-05-16).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see app/Concerns/HasBusinessScope.php
 * @see app/Concerns/BelongsToBusinessViaParent.php
 */

it('Entities Accounting Tier 0 declaram trait HasBusinessScope ou BelongsToBusinessViaParent', function () {
    // Entities Tier 0 com business_id direto (HasBusinessScope) ou child via FK chain
    // (BelongsToBusinessViaParent). Lista NÃO inclui:
    //  - AccountSubtype/AccountDetailType/PaymentType: semântica biz=0 catálogo plataforma (Wave 13 doc)
    //  - User/Contact/Business/BusinessLocation/Transaction/Currency: extends App\* core (herdam scope parent)
    //  - Reference data: AccountType, Country, Gender, MaritalStatus, ClientType, etc.
    $tier0Entities = [
        // HasBusinessScope (biz direto) — Wave 12+13 + Wave 15 confirma
        \Modules\Accounting\Entities\Account::class             => \App\Concerns\HasBusinessScope::class,
        \Modules\Accounting\Entities\BranchCapital::class       => \App\Concerns\HasBusinessScope::class,
        \Modules\Accounting\Entities\Budget::class              => \App\Concerns\HasBusinessScope::class,
        \Modules\Accounting\Entities\ChartOfAccount::class      => \App\Concerns\HasBusinessScope::class,
        // BelongsToBusinessViaParent (child via FK) — Wave 15 D1 MT rescue
        \Modules\Accounting\Entities\AccountTransaction::class  => \App\Concerns\BelongsToBusinessViaParent::class,
        \Modules\Accounting\Entities\JournalEntry::class        => \App\Concerns\BelongsToBusinessViaParent::class,
        \Modules\Accounting\Entities\Transfer::class            => \App\Concerns\BelongsToBusinessViaParent::class,
    ];

    foreach ($tier0Entities as $entity => $expectedTrait) {
        $traits = array_values(class_uses_recursive($entity));
        expect($traits)
            ->toContain($expectedTrait);
    }
});

it('AccountTransaction (child via account) declara businessParentRelation correto', function () {
    $model = new AccountTransaction();
    $reflection = new ReflectionClass($model);
    $prop = $reflection->getProperty('businessParentRelation');
    expect($prop->getValue($model))->toBe('account');
});

it('JournalEntry (child via business_location) declara businessParentRelation correto', function () {
    $model = new \Modules\Accounting\Entities\JournalEntry();
    $reflection = new ReflectionClass($model);
    $prop = $reflection->getProperty('businessParentRelation');
    expect($prop->getValue($model))->toBe('business_location');
});

it('Transfer (child via transfer_by user) declara businessParentRelation correto', function () {
    $model = new \Modules\Accounting\Entities\Transfer();
    $reflection = new ReflectionClass($model);
    $prop = $reflection->getProperty('businessParentRelation');
    expect($prop->getValue($model))->toBe('transfer_by');
});
