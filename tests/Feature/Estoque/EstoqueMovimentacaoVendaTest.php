<?php

declare(strict_types=1);

// @covers-us UC-EST-01

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-01 — VENDA vira `final` → SAI do estoque (DOC-RAIZ-ESTOQUE §3, linha `sell`).
 *
 * Contrato (NÃO derivado do código — da matriz §3 + orquestrador documentado §2):
 *   - Ao transicionar RASCUNHO→FINAL, o saldo de cada linha vendida DECREMENTA pela
 *     quantidade vendida (via ProductUtil::adjustProductStockForInvoice → decreaseProductQuantity).
 *   - Ao ESTORNAR (FINAL→RASCUNHO) uma linha existente, o saldo VOLTA (updateProductQuantity +qty).
 * É o movimento de estoque MAIS frequente em prod (POS/venda) e até hoje não tinha um
 * teste afirmando o delta (pedido Wagner 2026-07-02).
 *
 * Roda na lane MySQL (estoque-pest.yml) + CT 100 com biz=1 dogfood (ADR 0101). Em sqlite
 * :memory: faz skip gracioso — o schema UltimatePOS não existe lá.
 *
 * @see app/Utils/ProductUtil.php::adjustProductStockForInvoice
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('venda vira FINAL → baixa qty_available pela quantidade vendida', function () {
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

    // status_before=draft, transaction->status=final → decrementa (venda SAI).
    (new ProductUtil)->adjustProductStockForInvoice('draft', (object) ['status' => 'final'], $input, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(7.0);
});

it('venda estornada (FINAL→RASCUNHO) devolve o estoque da linha existente', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 7.0);

    $input = [
        'location_id' => $loc,
        'products' => [[
            'transaction_sell_lines_id' => 1, // linha já existente sendo estornada
            'product_id' => $produto->productId,
            'variation_id' => $produto->variationId(),
            'quantity' => 3.0,
        ]],
    ];

    // status_before=final, transaction->status=draft → devolve (estoque VOLTA).
    (new ProductUtil)->adjustProductStockForInvoice('final', (object) ['status' => 'draft'], $input, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});

it('saldo é por VARIAÇÃO: vender a variação A não mexe o saldo da variação B', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::variableProduct($this->biz, 2);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0); // variação A
    EstoqueFixture::setStock($produto, 1, $loc, 10.0); // variação B

    $input = [
        'location_id' => $loc,
        'products' => [[
            'product_id' => $produto->productId,
            'variation_id' => $produto->variationId(0), // vende só A
            'quantity' => 4.0,
        ]],
    ];

    (new ProductUtil)->adjustProductStockForInvoice('draft', (object) ['status' => 'final'], $input, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(6.0);  // A baixou
    expect(EstoqueFixture::currentStock($produto, 1, $loc))->toBe(10.0); // B intacta
});
