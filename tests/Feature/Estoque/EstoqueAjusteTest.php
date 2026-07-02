<?php

declare(strict_types=1);

// @covers-us UC-EST-05

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-05 — AJUSTE de estoque → SAI (normal) / reverte ao deletar. DOC-RAIZ-ESTOQUE §3 `stock_adjustment`.
 *
 * Contrato (matriz §3): um ajuste de saída DECREMENTA `qty_available` pela quantidade ajustada
 * (StockAdjustmentController@store → `decreaseProductQuantity`, ProductUtil.php:~339); DELETAR o
 * ajuste REVERTE (devolve) o saldo (via `updateProductQuantity`).
 *
 * NÍVEL: mutador (`decreaseProductQuantity`/`updateProductQuantity`) — o caminho de produção que
 * o ajuste executa. biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/ProductUtil.php::decreaseProductQuantity
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('ajuste de saída BAIXA o saldo pela quantidade ajustada', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    (new ProductUtil)->decreaseProductQuantity($produto->productId, $produto->variationId(), $loc, 4.0);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(6.0);
});

it('deletar o ajuste REVERTE (devolve) o saldo ajustado', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 6.0); // já ajustado -4 de 10

    // Reversão do ajuste deletado = updateProductQuantity com delta positivo.
    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(), 4.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});
