<?php

declare(strict_types=1);

// @covers-us UC-EST-03

use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-03 — DEVOLUÇÃO DE VENDA → ENTRA (volta pro estoque). DOC-RAIZ-ESTOQUE §3, linha `sell_return`.
 *
 * Contrato (matriz §3): registrar a devolução de venda REINTEGRA `qty_available` no LOCAL
 * da venda, pela quantidade devolvida — via TransactionUtil::addSellReturn, que chama
 * ProductUtil::updateProductQuantity (TransactionUtil.php:6189). É o caminho CANÔNICO
 * UltimatePOS de devolução (o daily-prod de venda balcão).
 *
 * ⚠️ ESCOPO: este teste cobre o fluxo NÚCLEO (`addSellReturn`). O módulo Vestuario tem um
 * caminho PARALELO (`Modules/Vestuario/Services/DevolucaoService`) sob suspeita de NÃO
 * reintegrar estoque — investigado à parte e, se confirmado, vira red-spec + flag Wagner
 * (regra mestre VALOR/ESTOQUE, proibicoes.md). Aqui provamos que o núcleo reintegra.
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see app/Utils/TransactionUtil.php::addSellReturn (linha 6189 → updateProductQuantity)
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    session(['user.business_id' => $this->biz]);
});

it('devolução de venda REINTEGRA qty_available pela quantidade devolvida', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    // Saldo pós-venda = 8 (a venda já baixou); devolver 2 deve voltar pra 10.
    EstoqueFixture::setStock($produto, 0, $loc, 8.0);

    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    $input = [
        'transaction_id' => $venda['transaction_id'],
        'discount_type' => 'fixed',
        'discount_amount' => 0,
        'products' => [[
            'sell_line_id' => $venda['sell_line_id'],
            'quantity' => 2.0,
            'unit_price_inc_tax' => 20.0,
        ]],
    ];

    app(TransactionUtil::class)->addSellReturn($input, $this->biz, $this->userId, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});

it('devolução parcial reintegra só o que foi devolvido (não a venda inteira)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0); // vendeu 5, devolve só 1

    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    $input = [
        'transaction_id' => $venda['transaction_id'],
        'discount_type' => 'fixed',
        'discount_amount' => 0,
        'products' => [[
            'sell_line_id' => $venda['sell_line_id'],
            'quantity' => 1.0,
            'unit_price_inc_tax' => 20.0,
        ]],
    ];

    app(TransactionUtil::class)->addSellReturn($input, $this->biz, $this->userId, false);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(6.0);
});
