<?php

declare(strict_types=1);

// @covers-us UC-INV-02

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-INV-02 — INV-2: RASCUNHO/COTAÇÃO não movimenta estoque. DOC-RAIZ-ESTOQUE §7 (INV-2).
 *
 * Contrato (§7 INV-2): só status TERMINAL (`final` venda, `received` compra) mexe saldo. Uma
 * venda que fica em RASCUNHO — ou uma COTAÇÃO — NÃO pode tocar `qty_available`. Provado pelo
 * orquestrador real `adjustProductStockForInvoice`: sem a transição draft→final, o saldo é intocado.
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/ProductUtil.php::adjustProductStockForInvoice
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §7 (INV-2)
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('venda que fica em RASCUNHO (draft→draft) NÃO movimenta o saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    $input = [
        'location_id' => $loc,
        'products' => [[
            'product_id' => $produto->productId,
            'variation_id' => $produto->variationId(),
            'quantity' => 3.0,
        ]],
    ];

    // Sem transição pra terminal: draft→draft = nenhum branch de movimento.
    (new ProductUtil)->adjustProductStockForInvoice('draft', (object) ['status' => 'draft'], $input, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});

it('COTAÇÃO (quotation) não movimenta o saldo', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    $input = [
        'location_id' => $loc,
        'products' => [[
            'product_id' => $produto->productId,
            'variation_id' => $produto->variationId(),
            'quantity' => 3.0,
        ]],
    ];

    (new ProductUtil)->adjustProductStockForInvoice('quotation', (object) ['status' => 'quotation'], $input, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});
