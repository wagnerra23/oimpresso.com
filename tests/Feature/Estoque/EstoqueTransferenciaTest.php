<?php

declare(strict_types=1);

// @covers-us UC-EST-06

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-06 — TRANSFERÊNCIA entre locais → SAI da origem + ENTRA no destino.
 * DOC-RAIZ-ESTOQUE §3 (`sell_transfer` + `purchase_transfer`).
 *
 * Contrato (matriz §3): transferir N unidades DECREMENTA o saldo da location de ORIGEM e
 * INCREMENTA o saldo da location de DESTINO, pela mesma quantidade — sem criar nem destruir
 * saldo total (StockTransferController@store → `decreaseProductQuantity`(origem) +
 * `updateProductQuantity`(destino)).
 *
 * Este teste é NÃO-tautológico: prova o par de dois lados + a especificidade por LOCAL (um
 * terceiro local não é tocado + a soma total se conserva). biz=1 dogfood (ADR 0101).
 *
 * @see app/Utils/ProductUtil.php::decreaseProductQuantity / ::updateProductQuantity
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('transferência SAI da origem e ENTRA no destino pela mesma quantidade', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $origem, 10.0);
    EstoqueFixture::setStock($produto, 0, $destino, 2.0);

    $util = new ProductUtil;
    // Transferir 3: sai origem, entra destino.
    $util->decreaseProductQuantity($produto->productId, $produto->variationId(), $origem, 3.0);
    $util->updateProductQuantity($destino, $produto->productId, $produto->variationId(), 3.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(7.0);  // origem baixou
    expect(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(5.0); // destino subiu
    // Conservação: total (origem+destino) inalterado (12).
    expect(EstoqueFixture::totalStock($produto))->toBe(12.0);
});

it('transferência não toca um TERCEIRO local do mesmo produto', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-DEST');
    $outro = EstoqueFixture::locationId($this->biz, '-OUTRO');
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $origem, 10.0);
    EstoqueFixture::setStock($produto, 0, $destino, 0.0);
    EstoqueFixture::setStock($produto, 0, $outro, 5.0);

    $util = new ProductUtil;
    $util->decreaseProductQuantity($produto->productId, $produto->variationId(), $origem, 4.0);
    $util->updateProductQuantity($destino, $produto->productId, $produto->variationId(), 4.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $outro))->toBe(5.0); // terceiro local intacto
});
