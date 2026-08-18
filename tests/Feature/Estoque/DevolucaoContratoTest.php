<?php

declare(strict_types=1);

// @covers-us CU-DEV-03 CU-DEV-04 CU-DEV-05

use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * Contrato da DEVOLUÇÃO de venda — o que ainda não tinha dono.
 *
 * ⚠️ ESCOPO — leia antes de somar caso aqui. A *reintegração* de saldo já é contrato de
 * `UC-EST-03` (EstoqueDevolucaoVendaTest) e `UC-EST-04` (EstoqueDevolucaoVestuarioTest).
 * Este arquivo cobre SÓ o delta: a forma de upsert, o cálculo do valor e a exclusão.
 *
 *   CU-DEV-03 `[E0]` — 2ª devolução SUBSTITUI a 1ª (caracterização do comportamento atual)
 *   CU-DEV-04 `[V0]` — valor da devolução não infla com desconto percentual (vetor num_uf)
 *   CU-DEV-05 `[E0]` — excluir a devolução devolve o estoque ao estado anterior
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANTI-VÁCUO
 * ─────────────────────────────────────────────────────────────────────────────
 * Cada contrato roda com um estado inicial ASSERTADO antes da ação. Um teste que só
 * afirma o estado final passaria verde por motivo errado (linha não encontrada, produto
 * sem saldo, devolução que nem gravou). Aqui o "antes" é medido, não assumido.
 * (proibicoes.md §5 2026-07-24 — "verde por não-execução")
 *
 * ⚠️ CU-DEV-03 é CARACTERIZAÇÃO, não endosso. Ele trava o comportamento ATUAL —
 * `$sell_line->quantity_returned = $quantity` (atribuição, TransactionUtil.php:6181) e
 * upsert por `return_parent_id` (6102). Se [W] decidir que devolução deve ACUMULAR, este
 * teste é o que vai ficar vermelho — e aí a mudança é REGRA MESTRE (eixo ESTOQUE:
 * dois caminhos + tabela antes→depois + OK [W]), nunca de carona.
 *
 * @see app/Utils/TransactionUtil.php::addSellReturn (6081-6192)
 * @see memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    session(['user.business_id' => $this->biz]);

    $this->devolver = function (array $venda, $quantidade, array $extra = [], bool $ufNumber = false) {
        $input = array_merge([
            'transaction_id' => $venda['transaction_id'],
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'products' => [[
                'sell_line_id' => $venda['sell_line_id'],
                'quantity' => $quantidade,
                'unit_price_inc_tax' => 20.0,
            ]],
        ], $extra);

        return app(TransactionUtil::class)->addSellReturn($input, $this->biz, $this->userId, $ufNumber);
    };

    $this->contarDevolucoes = fn (int $vendaId) => (int) DB::table('transactions')
        ->where('type', 'sell_return')
        ->where('return_parent_id', $vendaId)
        ->count();
});

it('CU-DEV-03 · segunda devolução SUBSTITUI a primeira (upsert por return_parent_id, quantidade atribuída)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0); // saldo pós-venda de 5
    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    // ANTES — medido, não assumido.
    expect(($this->contarDevolucoes)($venda['transaction_id']))->toBe(0);

    // 1ª devolução: 3 de 5.
    ($this->devolver)($venda, 3.0);
    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(8.0)
        ->and(($this->contarDevolucoes)($venda['transaction_id']))->toBe(1);

    // 2ª devolução: 2. NÃO acumula — substitui.
    ($this->devolver)($venda, 2.0);

    expect(($this->contarDevolucoes)($venda['transaction_id']))
        ->toBe(1, 'upsert: a 2a devolucao atualiza o mesmo sell_return, nao cria outro');

    $linha = DB::table('transaction_sell_lines')->find($venda['sell_line_id']);
    expect((float) $linha->quantity_returned)
        ->toBe(2.0, 'quantity_returned e ATRIBUIDO (=2), nao somado (nao vira 5)');

    // Estoque reflete 2 devolvidos sobre a base 5, não 3+2.
    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(7.0);
});

it('CU-DEV-04 · valor da devolução não infla com desconto percentual (num_uf, vetor do incidente 2026-06-05)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0, 227.90);

    // Caminho pt-BR REAL: o form manda string com vírgula e `$uf_number = true` parseia.
    $devolucao = ($this->devolver)($venda, '1', [
        'discount_type' => 'percentage',
        'discount_amount' => '10,05',
        'products' => [[
            'sell_line_id' => $venda['sell_line_id'],
            'quantity' => '1',
            'unit_price_inc_tax' => '227,90',
        ]],
    ], true);

    expect((float) $devolucao->total_before_tax)->toBe(227.90);

    // O invariante que o incidente violou: desconto REDUZ, nunca infla ~x100k.
    expect((float) $devolucao->final_total)
        ->toBeLessThanOrEqual((float) $devolucao->total_before_tax)
        ->and((float) $devolucao->final_total)->toBeGreaterThan(0.0)
        ->and(round((float) $devolucao->final_total, 5))->toBe(204.99605);
});

it('CU-DEV-05 · excluir a devolução devolve o estoque ao estado anterior', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    $devolucao = ($this->devolver)($venda, 3.0);
    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(8.0);

    // O CHOKEPOINT REAL — SellReturnController@destroy, via HTTP. Replicar aqui a lógica
    // dele (quantity_returned=0 + updateProductQuantity) faria um teste da CÓPIA: passaria
    // verde com o controller quebrado. (proibicoes.md §5 2026-08-14)
    $usuario = \App\User::find($this->userId);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_sell_return', 'guard_name' => 'web']);
    $usuario->givePermissionTo('access_sell_return');

    // `Util::num_f` lê `session('currency')[...]` sem fallback (Util.php:116-117), e o destroy
    // o chama. Em produção quem popula é o middleware SetSessionData; num request de teste a
    // sessão nasce vazia, `num_f` estoura e o `catch` do controller devolve um genérico
    // "algo deu errado" — que pareceria defeito do produto e é sessão incompleta. Semear via
    // withSession() replica produção SÓ neste request (no beforeEach global, quebra os outros
    // dois casos, que não passam por HTTP).
    $resposta = $this->actingAs($usuario)
        ->withSession([
            'user.business_id' => $this->biz,
            'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
            'business.currency_precision' => 2,
            'business.quantity_precision' => 2,
        ])
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->deleteJson("/sell-return/{$devolucao->id}");

    $resposta->assertOk()->assertJson(['success' => 1]);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))
        ->toBe(5.0, 'excluir a devolucao reverte o credito de estoque')
        ->and(($this->contarDevolucoes)($venda['transaction_id']))
        ->toBe(0, 'o sell_return sai da base');
});
