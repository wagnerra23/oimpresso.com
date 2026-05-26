<?php

declare(strict_types=1);

/**
 * US-COM-009 — Regressão R1 SQL guard (Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Cobertura paralela do hotfix R1: o bug original foi descoberto auditando
 * Compras (AUDIT-SENIOR-2026-05-25) mas o mesmo padrão `leftJoin('contacts AS
 * c'...)` sem scope existia em `getListExpenses` (alimenta `/expenses` listagem).
 * SELECT inclui `c.name as contact_name` — exposição cross-tenant IDÊNTICA via
 * filtro/busca na listagem de despesas.
 *
 * Hotfix também scopeou `expense_categories AS ec/esc` e `business_locations AS
 * bl` (todas têm coluna business_id — defense-in-depth ADR 0093 §G5).
 *
 * Caller chain afetado pelo hotfix:
 *   - app/Http/Controllers/ExpenseController.php (varia conforme rota)
 *
 * Refs:
 *   - ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL — Garantia 5
 *   - AUDIT-SENIOR-2026-05-25 Risk Register R1
 *   - app/Utils/TransactionUtil.php:4950+ (getListExpenses)
 *   - Modules/Compras/Tests/Feature/MultiTenantSqlGuardTest.php (test dedicado Compras)
 */

// Tests\TestCase já aplicado pelo `tests/Pest.php` global: uses(TestCase::class)->in('Feature').

beforeEach(function () {
    if (! class_exists(\App\Utils\TransactionUtil::class)) {
        $this->markTestSkipped('TransactionUtil ausente — verificar app/Utils/');
    }
});

function expenseGuardNormalizeSql(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

it('US-COM-009 (Expense): getListExpenses SQL inclui scope c.business_id + bl.business_id + ec.business_id no JOIN', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    $query = $util->getListExpenses(1);
    $sql = expenseGuardNormalizeSql($query->toSql());

    expect($sql)->toContain(
        'c.business_id',       // contacts AS c
        'bl.business_id',      // business_locations AS bl
        'ec.business_id'       // expense_categories AS ec
    );
});

it('US-COM-009 (Expense): getListExpenses SQL inclui scope esc.business_id (sub-category JOIN)', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    $query = $util->getListExpenses(1);
    $sql = expenseGuardNormalizeSql($query->toSql());

    // expense_categories AS esc também recebeu scope no hotfix.
    expect($sql)->toContain('esc.business_id');
});
