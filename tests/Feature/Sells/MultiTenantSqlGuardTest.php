<?php

declare(strict_types=1);

/**
 * US-COM-009 — Regressão R1 SQL guard (Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Cobertura paralela do hotfix R1: o bug original foi descoberto auditando
 * Compras (AUDIT-SENIOR-2026-05-25) mas o mesmo padrão `leftJoin('contacts'
 * ...)` sem scope existia em `getListSells` (alimenta `/sells` index). SELECT
 * inclui `contacts.name`, `contacts.mobile`, `contacts.contact_id`,
 * `contacts.supplier_business_name` — exposição cross-tenant IDÊNTICA via
 * filtro/busca na listagem de vendas.
 *
 * Caller chain afetado pelo hotfix:
 *   - app/Http/Controllers/SellController.php:114 → $this->transactionUtil->getListSells()
 *   - app/SellController.php:89 (legacy)
 *   - Modules/Crm/Http/Controllers/SellController.php:50
 *   - Modules/Crm/Http/Controllers/OrderRequestController.php:82
 *
 * Refs:
 *   - ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL — Garantia 5
 *   - AUDIT-SENIOR-2026-05-25 Risk Register R1
 *   - app/Utils/TransactionUtil.php:5007+ (getListSells)
 *   - Modules/Compras/Tests/Feature/MultiTenantSqlGuardTest.php (test dedicado Compras)
 */

// Tests\TestCase já aplicado pelo `tests/Pest.php` global: uses(TestCase::class)->in('Feature').

beforeEach(function () {
    if (! class_exists(\App\Utils\TransactionUtil::class)) {
        $this->markTestSkipped('TransactionUtil ausente — verificar app/Utils/');
    }
});

function sellsGuardNormalizeSql(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

it('US-COM-009 (Sells): getListSells SQL inclui scope contacts.business_id + bl.business_id no JOIN', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    $query = $util->getListSells(1, 'sell');
    $sql = sellsGuardNormalizeSql($query->toSql());

    expect($sql)->toContain(
        'contacts.business_id',
        'bl.business_id'
    );
});

it('US-COM-009 (Sells): getListSells funciona pra sale_type variantes (sales_order, draft)', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    foreach (['sell', 'sales_order', 'draft'] as $type) {
        $sql = sellsGuardNormalizeSql($util->getListSells(1, $type)->toSql());

        expect($sql)->toContain(
            'contacts.business_id',
            'bl.business_id'
        )->and($sql)->toContain('transactions.type = ?');
    }
});
