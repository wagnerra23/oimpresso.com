<?php

declare(strict_types=1);

// @covers-us UC-EST-02

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-02 — COMPRA vira `received` → ENTRA no estoque (DOC-RAIZ-ESTOQUE §3, linha `purchase`).
 *
 * Contrato (matriz §3 + §2): o recebimento de compra SOMA `qty_available` pela quantidade
 * recebida, no LOCAL de destino e na VARIAÇÃO comprada, via ProductUtil::updateProductQuantity
 * (o mutador de ENTRADA — `createOrUpdatePurchaseLines` o chama quando `status == 'received'`).
 *
 * NÍVEL DO TESTE: mutador de entrada (`updateProductQuantity`) — é o caminho de produção
 * que a compra recebida executa (ProductUtil.php:1234). Um teste do fluxo completo
 * (`createOrUpdatePurchaseLines` provando que RASCUNHO não entra e RECEIVED entra) é o
 * reforço não-tautológico da decisão de status — rastreado como follow-up no casos.md
 * (UC-EST-02b, Status 🧪). Este aqui já trava o delta que hoje NENHUM teste afirma.
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite (schema UltimatePOS ausente).
 *
 * @see app/Utils/ProductUtil.php::updateProductQuantity / ::createOrUpdatePurchaseLines
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('compra recebida ENTRA: qty_available soma a quantidade recebida', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    // Entrada de compra recebida = updateProductQuantity com delta positivo (uf_data=false).
    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(), 5.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(15.0);
});

it('recebimento ACUMULA sobre o saldo existente (não sobrescreve)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 15.0);

    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(), 5.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(20.0);
});

it('recebimento de produto novo (sem VLD prévia) CRIA a linha de saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    // Sem setStock: não há VLD ainda. O mutador deve criar a linha em 0 e somar.

    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(), 8.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(8.0);
});

it('recebimento de VARIÁVEL entra só na variação recebida', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::variableProduct($this->biz, 2);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);
    EstoqueFixture::setStock($produto, 1, $loc, 10.0);

    (new ProductUtil)->updateProductQuantity($loc, $produto->productId, $produto->variationId(0), 5.0, 0, null, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(15.0); // recebida
    expect(EstoqueFixture::currentStock($produto, 1, $loc))->toBe(10.0); // intacta
});
