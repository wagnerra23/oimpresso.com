<?php

declare(strict_types=1);

// @covers-us UC-EST-08

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-08 — FABRICAÇÃO / KIT → consome COMPONENTES + produz ACABADO. DOC-RAIZ-ESTOQUE §2/§3.
 *
 * Contrato: montar um kit / produzir um acabado a partir de uma receita CONSOME o saldo de cada
 * componente pela quantidade da receita (ProductUtil::decreaseProductQuantityCombo — o mutador de
 * decomposição de combo/kit) e ENTRA o saldo do acabado (`updateProductQuantity`).
 *
 * Este teste prova a DECOMPOSIÇÃO (cada componente baixa pela sua quantidade de receita, não
 * uniforme) — não é tautológico. O fluxo completo do Modules/Manufacturing (ProductionController
 * com receita/BOM real) é reforço rastreado como follow-up (UC-EST-08b no casos.md).
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/ProductUtil.php::decreaseProductQuantityCombo / ::updateProductQuantity
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §2 §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('fabricar um kit CONSOME cada componente pela quantidade da receita', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $compA = EstoqueFixture::singleProduct($this->biz);
    $compB = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($compA, 0, $loc, 20.0);
    EstoqueFixture::setStock($compB, 0, $loc, 20.0);

    // Receita: 2 de A + 1 de B por unidade — decompõe e baixa cada componente.
    $comboDetails = [
        ['product_id' => $compA->productId, 'variation_id' => $compA->variationId(), 'quantity' => 2.0],
        ['product_id' => $compB->productId, 'variation_id' => $compB->variationId(), 'quantity' => 1.0],
    ];

    (new ProductUtil)->decreaseProductQuantityCombo($comboDetails, $loc);

    expect(EstoqueFixture::currentStock($compA, 0, $loc))->toBe(18.0); // -2
    expect(EstoqueFixture::currentStock($compB, 0, $loc))->toBe(19.0); // -1 (quantidades distintas)
});

it('produzir o acabado ENTRA o saldo do produto final', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $acabado = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($acabado, 0, $loc, 0.0);

    // Produção de 5 unidades do acabado.
    (new ProductUtil)->updateProductQuantity($loc, $acabado->productId, $acabado->variationId(), 5.0, 0, null, false);

    expect(EstoqueFixture::currentStock($acabado, 0, $loc))->toBe(5.0);
});
