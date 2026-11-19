<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Auditoria RecurringBilling d5 (2026-11-19), no schema REAL:
 *  - as 8 tabelas do módulo que só tinham índice em business_id passam a ter FK para `business`
 *    (migration 2026_11_19_110000);
 *  - a idempotência de pg_webhook_events é por empresa: unique(business_id, provider, event_id)
 *    (migration 2026_11_19_110100), e a chave antiga sem a empresa saiu.
 *
 * ⚠️ SKIP fora do MySQL (as FKs não existem no SQLite dos testes): leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
});

it('d5 · as 8 tabelas do módulo têm FK de business_id para business', function () {
    $tabelas = ['rb_plans', 'rb_subscriptions', 'rb_invoices', 'rb_charge_attempts', 'rb_boleto_credentials',
        'rb_subscription_events', 'rb_subscription_favorites', 'rb_subscription_notes'];

    foreach ($tabelas as $tabela) {
        $fks = collect(Schema::getForeignKeys($tabela))
            ->filter(fn ($fk) => $fk['columns'] === ['business_id'] && $fk['foreign_table'] === 'business');
        expect($fks)->toHaveCount(1, "{$tabela} sem FK business_id → business");
    }

    // Controle: a FK que já existia antes (rb_cartoes_salvos) é achada pelo mesmo critério.
    expect(collect(Schema::getForeignKeys('rb_cartoes_salvos'))
        ->filter(fn ($fk) => $fk['columns'] === ['business_id'])->count())->toBe(1);
});

it('d5 · pg_webhook_events: chave de idempotência inclui a empresa e a antiga saiu', function () {
    expect(Schema::hasIndex('pg_webhook_events', 'pg_webhook_idem_biz'))->toBeTrue()
        ->and(Schema::hasIndex('pg_webhook_events', 'pg_webhook_idempotency'))->toBeFalse();

    $idx = collect(Schema::getIndexes('pg_webhook_events'))->firstWhere('name', 'pg_webhook_idem_biz');
    expect($idx['columns'])->toBe(['business_id', 'provider', 'event_id'])
        ->and($idx['unique'])->toBeTrue();
});
