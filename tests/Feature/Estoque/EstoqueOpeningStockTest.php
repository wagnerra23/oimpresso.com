<?php

declare(strict_types=1);

// @covers-us UC-EST-07

use App\Product;
use App\User;
use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-07 — ESTOQUE INICIAL (opening) → ENTRA. DOC-RAIZ-ESTOQUE §3 `opening_stock`.
 *
 * Contrato (matriz §3): informar estoque inicial de um produto num local CRIA o saldo pela
 * quantidade informada (ProductUtil::addSingleProductOpeningStock → `updateProductQuantity` +
 * Transaction `opening_stock`). Fluxo REAL (não só mutador).
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/ProductUtil.php::addSingleProductOpeningStock (linha ~1166 → updateProductQuantity)
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);

    // addSingleProductOpeningStock → num_uf/num_f (Util.php) leem session('currency') pra
    // separadores + precisão. Fora de request HTTP o SetSessionData não roda, então setamos
    // o mínimo (senão "array offset on null" em Util::num_f).
    session([
        'currency' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'symbol' => 'R$', 'code' => 'BRL'],
        'business.currency_precision' => 2,
        'business.quantity_precision' => 2,
    ]);

    // addSingleProductOpeningStock → BusinessLocation::forDropdown (check_permission=true) →
    // auth()->user()->permitted_locations() → precisa de user autenticado com
    // `access_all_locations` pra a location de teste entrar no dropdown válido.
    $user = User::findOrFail(EstoqueFixture::userId($this->biz));
    Permission::firstOrCreate(['name' => 'access_all_locations', 'guard_name' => 'web']);
    $user->givePermissionTo('access_all_locations');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
});

it('estoque inicial (opening) ENTRA a quantidade informada no local', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $handle = EstoqueFixture::singleProduct($this->biz);
    // produto sem VLD ainda — opening cria o saldo.

    $product = Product::with(['variations', 'product_tax'])->findOrFail($handle->productId);

    (new ProductUtil)->addSingleProductOpeningStock(
        $this->biz,
        $product,
        [$loc => ['purchase_price' => '5', 'quantity' => '10']],
        date('Y-m-d'),
        EstoqueFixture::userId($this->biz),
    );

    expect(EstoqueFixture::currentStock($handle, 0, $loc))->toBe(10.0);
});
