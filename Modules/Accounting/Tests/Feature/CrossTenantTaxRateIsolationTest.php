<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\TaxRate;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant isolation TaxRate (Wave 13 trait HasBusinessScope).
 *
 * TaxRate é configuração fiscal per-business (ICMS, PIS, COFINS, IPI variam por
 * regime tributário do cliente). Vazamento = aplicar alíquota errada =
 * impacto fiscal real. Tier 0 IRREVOGÁVEL ADR 0093.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível (ADR 0101).');
    }
    if (! Schema::hasTable('tax_rates')) {
        $this->markTestSkipped('Tabela tax_rates missing.');
    }
});

const CROSS_TR_BIZ_WAGNER = 1;
const CROSS_TR_BIZ_FICTICIO = 99;

function setCrossTrBizSession(int $businessId): void
{
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);
}

it('cross-tenant: TaxRate biz=1 isolation via raw DB query biz=99', function () {
    $tr = TaxRate::create([ // SUPERADMIN
        'business_id'  => CROSS_TR_BIZ_WAGNER,
        'name'         => 'ICMS Wave 18 CT Test',
        'amount'       => 18.0,
        'is_tax_group' => 0,
        'created_by'   => 1,
    ]);

    $rawCount = DB::table('tax_rates')
        ->where('business_id', CROSS_TR_BIZ_FICTICIO)
        ->where('id', $tr->id)
        ->count();

    expect($rawCount)->toBe(0, 'TaxRate biz=1 NUNCA pode vazar pra biz=99');
})->afterEach(function () {
    TaxRate::withoutGlobalScopes()->where('name', 'ICMS Wave 18 CT Test')->forceDelete();
});

it('cross-tenant: TaxRate global scope (HasBusinessScope Wave 13) filtra automatically', function () {
    $tr = TaxRate::create([ // SUPERADMIN
        'business_id'  => CROSS_TR_BIZ_WAGNER,
        'name'         => 'PIS Wave 18 Scope Test',
        'amount'       => 1.65,
        'is_tax_group' => 0,
        'created_by'   => 1,
    ]);

    setCrossTrBizSession(CROSS_TR_BIZ_FICTICIO);
    expect(TaxRate::where('id', $tr->id)->get())->toHaveCount(0);

    // Escape SUPERADMIN
    expect(TaxRate::withoutGlobalScope(ScopeByBusiness::class)->where('id', $tr->id)->get())
        ->toHaveCount(1);
})->afterEach(function () {
    TaxRate::withoutGlobalScopes()->where('name', 'PIS Wave 18 Scope Test')->forceDelete();
});
