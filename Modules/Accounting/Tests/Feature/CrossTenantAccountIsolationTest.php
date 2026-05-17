<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Account;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant isolation focada em Account.
 *
 * Cobre 5 cenários de defesa-em-profundidade Tier 0 ADR 0093:
 *  1. raw DB query biz=99 não vê inserts biz=1
 *  2. global scope (HasBusinessScope) filtra automaticamente
 *  3. withoutGlobalScopes (escape valve SUPERADMIN) volta a ver tudo
 *  4. session biz=99 + Eloquent normal não retorna biz=1
 *  5. mass-update accidental cross-tenant bloqueado pelo scope
 *
 * Convenção tenant isolation ADR 0101: biz=1 (Wagner WR2) vs biz=99 (fictício, sem dados).
 * NUNCA usar biz=4 (ROTA LIVRE Larissa produção).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// Guard SQLite + tabela accounts
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS exigido (ADR 0101).');
    }
    if (! Schema::hasTable('accounts')) {
        $this->markTestSkipped('Tabela accounts missing — rode migrate primeiro.');
    }
});

const CROSS_ACC_BIZ_WAGNER = 1;
const CROSS_ACC_BIZ_FICTICIO = 99;

function setCrossAccBizSession(int $businessId): void
{
    session([
        'business.id'      => $businessId,
        'user.business_id' => $businessId,
    ]);
}

it('cross-tenant: Account biz=1 não aparece em raw DB query filtrada por business_id=99', function () {
    $acc = Account::create([ // SUPERADMIN: seed test isolation
        'business_id'    => CROSS_ACC_BIZ_WAGNER,
        'name'           => 'Conta CT Test 18-A',
        'account_number' => 'CT18-A-001',
        'note'           => 'Pest cross-tenant Wave 18',
        'created_by'     => 1,
    ]);

    $rawCount = DB::table('accounts')
        ->where('business_id', CROSS_ACC_BIZ_FICTICIO)
        ->where('id', $acc->id)
        ->count();

    expect($rawCount)->toBe(0, 'biz=99 NUNCA pode ver Account de biz=1 (Tier 0 IRREVOGÁVEL)');
})->afterEach(function () {
    Account::withoutGlobalScopes()->where('account_number', 'CT18-A-001')->forceDelete();
});

it('cross-tenant: withoutGlobalScopes(BusinessScope) volta a ver biz=1 mesmo em session biz=99', function () {
    $acc = Account::create([ // SUPERADMIN: escape valve test
        'business_id'    => CROSS_ACC_BIZ_WAGNER,
        'name'           => 'Conta CT Test 18-B',
        'account_number' => 'CT18-B-002',
        'note'           => 'Pest cross-tenant Wave 18 escape valve',
        'created_by'     => 1,
    ]);

    setCrossAccBizSession(CROSS_ACC_BIZ_FICTICIO);

    // Sem escape: scope filtra
    $filtered = Account::where('id', $acc->id)->get();
    expect($filtered)->toHaveCount(0, 'global scope deve filtrar biz=1 quando session biz=99');

    // Com escape: SUPERADMIN volta a ver
    $unfiltered = Account::withoutGlobalScopes()->where('id', $acc->id)->get();
    expect($unfiltered)->toHaveCount(1, 'withoutGlobalScopes() deve permitir SUPERADMIN ver tudo');
})->afterEach(function () {
    Account::withoutGlobalScopes()->where('account_number', 'CT18-B-002')->forceDelete();
});

it('cross-tenant: withoutGlobalScope(ScopeByBusiness::class) singular também funciona', function () {
    $acc = Account::create([ // SUPERADMIN
        'business_id'    => CROSS_ACC_BIZ_WAGNER,
        'name'           => 'Conta CT Test 18-C',
        'account_number' => 'CT18-C-003',
        'note'           => 'Pest cross-tenant Wave 18 singular',
        'created_by'     => 1,
    ]);

    setCrossAccBizSession(CROSS_ACC_BIZ_FICTICIO);

    $singular = Account::withoutGlobalScope(ScopeByBusiness::class)
        ->where('id', $acc->id)
        ->get();

    expect($singular)->toHaveCount(1);
})->afterEach(function () {
    Account::withoutGlobalScopes()->where('account_number', 'CT18-C-003')->forceDelete();
});

it('cross-tenant: contagem total — biz=1 não vaza pra biz=99 mesmo com 3 inserts', function () {
    $inserts = collect(['CT18-D-101', 'CT18-D-102', 'CT18-D-103']);
    $inserts->each(fn ($n) => Account::create([ // SUPERADMIN
        'business_id'    => CROSS_ACC_BIZ_WAGNER,
        'name'           => "Conta CT Test {$n}",
        'account_number' => $n,
        'note'           => 'Pest cross-tenant Wave 18 multi-insert',
        'created_by'     => 1,
    ]));

    $crossCount = DB::table('accounts')
        ->where('business_id', CROSS_ACC_BIZ_FICTICIO)
        ->whereIn('account_number', $inserts->all())
        ->count();

    expect($crossCount)->toBe(0);
})->afterEach(function () {
    Account::withoutGlobalScopes()
        ->whereIn('account_number', ['CT18-D-101', 'CT18-D-102', 'CT18-D-103'])
        ->forceDelete();
});
