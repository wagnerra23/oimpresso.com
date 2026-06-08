<?php

declare(strict_types=1);

/**
 * US-COM-009 — Regressão R1 SQL guard (Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Hotfix dedicado: testa diretamente `TransactionUtil::getListPurchases/Sells/
 * Expenses` via `->toSql()` SEM depender de schema/seeder. Roda em QUALQUER
 * ambiente (CI sqlite :memory:, dev mysql, prod). Isso garante invariante de
 * regressão futura mesmo em refactor que reverta closure sem quebrar HTTP path.
 *
 * Por que SqlGuard separado do MultiTenantTest:
 *   - MultiTenantTest tem beforeEach que markTestSkipped quando schema ausente.
 *   - Cenários 5/5b/5c só precisam de `->toSql()` (renderiza query plan, não
 *     executa). Não dependem de DB conectado nem tabelas seedadas.
 *   - Mover pra arquivo separado evita "skip cascade" e torna gate de CI mais
 *     forte: este teste DEVE rodar verde em sqlite mínimo + zero seeders.
 *
 * Contexto do leak:
 *   - app/Utils/TransactionUtil.php:4893+ — getListPurchases/Sells/Expenses
 *     fazia leftJoin('contacts'...) sem scope `contacts.business_id`. SELECT
 *     incluía `contacts.name`, `contacts.mobile`, `contacts.supplier_business_name`
 *     — vazamento cross-tenant via filtro `?q=` na listagem (MultiTenantTest
 *     cenário 4 reproduz). Hotfix substituiu join simples por closure com
 *     `->where('contacts.business_id', $business_id)`.
 *   - Defense-in-depth ADR 0093 §G5: business_locations, expense_categories
 *     também receberam scope mesmo pattern (todos têm coluna business_id).
 *
 * Refs:
 *   - ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL — Garantia 5 (CI lint
 *     detecta JOIN sem scope)
 *   - AUDIT-SENIOR-2026-05-25 Risk Register R1 (Compras)
 *   - MultiTenantTest cenário 4 (HTTP-level regression)
 *   - PR #1569 task US-COM-006 (Pest cross-tenant base)
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    if (! class_exists(\App\Utils\TransactionUtil::class)) {
        $this->markTestSkipped('TransactionUtil ausente — verificar app/Utils/');
    }
});

/**
 * Normaliza SQL pra ser DB-agnostic (sqlite usa "x"."y", mysql usa `x`.`y`).
 * Remove backticks e aspas duplas — facilita assertion textual cross-driver.
 */
function comprasGuardNormalizeSql(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

it('US-COM-009 cenario 5: getListPurchases SQL inclui scope contacts.business_id + BS.business_id no JOIN', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    $query = $util->getListPurchases(1);
    $sql = comprasGuardNormalizeSql($query->toSql());

    // Verifica que JOIN contacts agora tem scope `contacts.business_id`.
    // Pré-fix (smell): left join contacts on transactions.contact_id = contacts.id
    // Pós-fix:        left join contacts on transactions.contact_id = contacts.id
    //                  and contacts.business_id = ?
    // toContain do Pest aceita múltiplos needles (todos devem estar presentes).
    // Defense-in-depth secundária (ADR 0093 §G5): business_locations BS scopeado.
    expect($sql)->toContain(
        'contacts.business_id',
        'BS.business_id'
    );
});

it('US-COM-009 cenario 5b: getListSells SQL inclui scope contacts.business_id + bl.business_id no JOIN', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    // Mesmo bug existia em getListSells (SELECT inclui contacts.name/mobile/
    // supplier_business_name). Hotfix aplicou mesmo padrão closure.
    // Defense-in-depth secundária (ADR 0093 §G5): business_locations bl scopeado.
    $query = $util->getListSells(1);
    $sql = comprasGuardNormalizeSql($query->toSql());

    expect($sql)->toContain(
        'contacts.business_id',
        'bl.business_id'
    );
});

it('US-COM-009 cenario 5c: getListExpenses SQL inclui scope c.business_id + bl.business_id + ec.business_id no JOIN', function () {
    $util = app(\App\Utils\TransactionUtil::class);

    // Expense também tinha mesmo bug — SELECT inclui c.name as contact_name.
    // Defense-in-depth secundária (ADR 0093 §G5): business_locations bl +
    // expense_categories ec também scopeados (todas têm coluna business_id).
    $query = $util->getListExpenses(1);
    $sql = comprasGuardNormalizeSql($query->toSql());

    expect($sql)->toContain(
        'c.business_id',
        'bl.business_id',
        'ec.business_id'
    );
});
