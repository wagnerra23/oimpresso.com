<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Brands;
use Modules\Accounting\Entities\Category;
use Modules\Jana\Scopes\ScopeByBusiness;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1 saturação — cross-tenant Brands + Category (Wave 13 trait HasBusinessScope).
 *
 * Brands/Category são entidades de produto per-tenant. Vazamento = expor catálogo
 * de cliente A pra cliente B. Tier 0 IRREVOGÁVEL ADR 0093.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível (ADR 0101).');
    }
});

const CROSS_BC2_BIZ_WAGNER = 1;
const CROSS_BC2_BIZ_FICTICIO = 99;

function setCrossBc2BizSession(int $businessId): void
{
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);
}

it('cross-tenant: Brands biz=1 não vaza pra raw biz=99', function () {
    if (! Schema::hasTable('brands')) {
        $this->markTestSkipped('Tabela brands missing.');
    }
    $brand = Brands::create([ // SUPERADMIN
        'business_id' => CROSS_BC2_BIZ_WAGNER,
        'name'        => 'Brand Wave 18 CT Iso',
        'created_by'  => 1,
    ]);

    $rawCount = DB::table('brands')
        ->where('business_id', CROSS_BC2_BIZ_FICTICIO)
        ->where('id', $brand->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    Brands::withoutGlobalScopes()->where('name', 'Brand Wave 18 CT Iso')->forceDelete();
});

it('cross-tenant: Brands global scope filtra session biz=99', function () {
    if (! Schema::hasTable('brands')) {
        $this->markTestSkipped('Tabela brands missing.');
    }
    $brand = Brands::create([ // SUPERADMIN
        'business_id' => CROSS_BC2_BIZ_WAGNER,
        'name'        => 'Brand Wave 18 Scope',
        'created_by'  => 1,
    ]);

    setCrossBc2BizSession(CROSS_BC2_BIZ_FICTICIO);
    expect(Brands::where('id', $brand->id)->get())->toHaveCount(0);
    expect(Brands::withoutGlobalScopes()->where('id', $brand->id)->get())->toHaveCount(1);
})->afterEach(function () {
    Brands::withoutGlobalScopes()->where('name', 'Brand Wave 18 Scope')->forceDelete();
});

it('cross-tenant: Category biz=1 não vaza pra raw biz=99', function () {
    if (! Schema::hasTable('categories')) {
        $this->markTestSkipped('Tabela categories missing.');
    }
    $cat = Category::create([ // SUPERADMIN
        'business_id'   => CROSS_BC2_BIZ_WAGNER,
        'name'          => 'Cat Wave 18 CT Iso',
        'short_code'    => 'CAT18CT',
        'category_type' => 'product',
        'created_by'    => 1,
    ]);

    $rawCount = DB::table('categories')
        ->where('business_id', CROSS_BC2_BIZ_FICTICIO)
        ->where('id', $cat->id)
        ->count();

    expect($rawCount)->toBe(0);
})->afterEach(function () {
    Category::withoutGlobalScopes()->where('name', 'Cat Wave 18 CT Iso')->forceDelete();
});

it('cross-tenant: Category escape valve withoutGlobalScope(ScopeByBusiness)', function () {
    if (! Schema::hasTable('categories')) {
        $this->markTestSkipped('Tabela categories missing.');
    }
    $cat = Category::create([ // SUPERADMIN
        'business_id'   => CROSS_BC2_BIZ_WAGNER,
        'name'          => 'Cat Wave 18 Scope',
        'short_code'    => 'CAT18SC',
        'category_type' => 'product',
        'created_by'    => 1,
    ]);

    setCrossBc2BizSession(CROSS_BC2_BIZ_FICTICIO);
    expect(Category::where('id', $cat->id)->get())->toHaveCount(0);
    expect(Category::withoutGlobalScope(ScopeByBusiness::class)->where('id', $cat->id)->get())
        ->toHaveCount(1);
})->afterEach(function () {
    Category::withoutGlobalScopes()->where('name', 'Cat Wave 18 Scope')->forceDelete();
});
