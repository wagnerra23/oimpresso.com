<?php

declare(strict_types=1);

use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EstoqueFixture;

/**
 * Linha de pedido e linha de venda de outra empresa não entram na venda (Tier 0, ADR 0093).
 *
 * Caso 212 da lista de pendências de 30/10/2026: a venda que fatura um pedido manda o id da
 * linha do pedido (`so_line_id`), e `TransactionUtil::updateSalesOrderLine` achava a linha só
 * pelo id. `transaction_sell_lines` não tem `business_id` nem escopo global, então um id de
 * outra empresa mudava a quantidade faturada do pedido dela. O irmão no mesmo método: o id da
 * linha editada (`transaction_sell_lines_id`) também vinha do formulário sem conferência.
 *
 * Âncora: ADR 0093. O ponto exercitado é `createOrUpdateSellLines`, o mesmo que a venda e a
 * edição chamam.
 *
 * Anti-vácuo: o controle com a linha do próprio pedido prova que o caminho roda e soma a
 * quantidade faturada; sem ele, o "não mudou" dos outros casos passaria com o código parado.
 *
 * Tenant: 98 (EstoqueFixture::businessId, ADR 0358) contra o 2º business semeado. Nunca biz=4.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outraBizId = EstoqueFixture::secondBusinessId();
    if (! $this->outraBizId) {
        $this->markTestSkipped('Sem o 2º business semeado para o caso entre empresas.');
    }

    session(['business.enabled_modules' => []]);
});

/** Pedido de venda com 1 linha de 5 unidades, nada faturado. */
function soLinePedido(int $bizId): array
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $pedido = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($bizId), 5, 10.0);
    DB::table('transactions')->where('id', $pedido['transaction_id'])->update(['type' => 'sales_order', 'status' => 'ordered']);
    DB::table('transaction_sell_lines')->where('id', $pedido['sell_line_id'])->update(['so_quantity_invoiced' => 0]);

    return $pedido;
}

/** Venda sem linhas, onde o item vai ser gravado. */
function soLineVenda(int $bizId): object
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($bizId), 1, 10.0);
    DB::table('transaction_sell_lines')->where('id', $venda['sell_line_id'])->delete();

    return (object) ['tx' => \App\Transaction::find($venda['transaction_id']), 'produto' => $produto];
}

/** Item da venda, faturando a linha de pedido informada. */
function soLineItem(object $venda, ?int $soLineId, float $qtd = 2): array
{
    return [
        'product_id' => $venda->produto->productId,
        'variation_id' => $venda->produto->variations[0]['variation_id'],
        'quantity' => $qtd,
        'unit_price' => 10.0,
        'unit_price_inc_tax' => 10.0,
        'item_tax' => 0,
        'tax_id' => null,
        'so_line_id' => $soLineId,
    ];
}

function soLineGravar(object $venda, array $item, ?string $statusAntes = null): void
{
    app(TransactionUtil::class)->createOrUpdateSellLines($venda->tx, [$item], $venda->tx->location_id, false, $statusAntes, [], false);
}

function soLineLinhaAvulsa(object $venda, float $qtd): int
{
    return (int) DB::table('transaction_sell_lines')->insertGetId([
        'transaction_id' => $venda->tx->id, 'product_id' => $venda->produto->productId,
        'variation_id' => $venda->produto->variations[0]['variation_id'], 'quantity' => $qtd, 'quantity_returned' => 0,
        'unit_price' => 10, 'unit_price_inc_tax' => 10, 'item_tax' => 0, 'so_quantity_invoiced' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('controle: faturar a linha do próprio pedido soma a quantidade faturada', function () {
    $pedido = soLinePedido($this->bizId);
    $venda = soLineVenda($this->bizId);

    soLineGravar($venda, soLineItem($venda, $pedido['sell_line_id']));

    expect((float) DB::table('transaction_sell_lines')->where('id', $pedido['sell_line_id'])->value('so_quantity_invoiced'))->toBe(2.0);
    expect((int) DB::table('transaction_sell_lines')->where('transaction_id', $venda->tx->id)->value('so_line_id'))->toBe($pedido['sell_line_id']);
});

it('linha de pedido de outra empresa não é faturada nem fica ligada à venda', function () {
    $pedidoAlheio = soLinePedido($this->outraBizId);
    $venda = soLineVenda($this->bizId);

    soLineGravar($venda, soLineItem($venda, $pedidoAlheio['sell_line_id']));

    // A venda gravou o item (a operação aconteceu) ...
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda->tx->id)->count())->toBe(1);
    // ... sem ligar nem mexer no pedido da outra empresa.
    expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda->tx->id)->value('so_line_id'))->toBeNull();
    expect((float) DB::table('transaction_sell_lines')->where('id', $pedidoAlheio['sell_line_id'])->value('so_quantity_invoiced'))->toBe(0.0);
});

it('linha que não é de pedido de venda não é faturada', function () {
    $linhaComum = soLineLinhaAvulsa(soLineVenda($this->bizId), 5);
    $venda = soLineVenda($this->bizId);

    soLineGravar($venda, soLineItem($venda, $linhaComum));

    expect(DB::table('transaction_sell_lines')->where('transaction_id', $venda->tx->id)->value('so_line_id'))->toBeNull();
    expect((float) DB::table('transaction_sell_lines')->where('id', $linhaComum)->value('so_quantity_invoiced'))->toBe(0.0);
});

it('editar com o id de uma linha de venda de outra empresa dá 404 e não mexe nela', function () {
    $linhaAlheia = soLineLinhaAvulsa(soLineVenda($this->outraBizId), 3);
    $venda = soLineVenda($this->bizId);

    $item = soLineItem($venda, null, 9) + ['transaction_sell_lines_id' => $linhaAlheia];

    expect(fn () => soLineGravar($venda, $item, 'draft'))->toThrow(HttpException::class);
    expect((float) DB::table('transaction_sell_lines')->where('id', $linhaAlheia)->value('quantity'))->toBe(3.0);
});
