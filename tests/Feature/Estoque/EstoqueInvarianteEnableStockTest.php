<?php

declare(strict_types=1);

// @covers-us UC-INV-05

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-INV-05 — INV-5: `enable_stock=0` NÃO movimenta saldo. DOC-RAIZ-ESTOQUE §7 (INV-5).
 *
 * Contrato (§7 INV-5 + §2): produto SEM controle de estoque (`enable_stock=0`) não tem o saldo
 * mexido pelos mutadores — `updateProductQuantity` e `decreaseProductQuantity` checam
 * `$product->enable_stock == 1` antes de tocar `qty_available`. Guard de produto-serviço.
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/ProductUtil.php::updateProductQuantity / ::decreaseProductQuantity (guard enable_stock)
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §7 (INV-5)
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('produto enable_stock=0: baixa (decreaseProductQuantity) NÃO mexe o saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz, enableStock: false);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0); // saldo semeado direto (VLD), mas produto é serviço

    (new ProductUtil)->decreaseProductQuantity($produto->productId, $produto->variationId(), $loc, 3.0);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0); // guard: intocado
});

it('produto enable_stock=0: entrada (updateProductQuantity) NÃO mexe o saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz, enableStock: false);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(), 5.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0); // guard: intocado
});

it('contraprova: o MESMO fluxo num produto enable_stock=1 MEXE o saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz, enableStock: true);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    (new ProductUtil)->decreaseProductQuantity($produto->productId, $produto->variationId(), $loc, 3.0);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(7.0); // controla estoque → baixa
});
