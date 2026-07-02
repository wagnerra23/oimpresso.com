<?php

declare(strict_types=1);

// @covers-us UC-EST-04

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\DevolucaoService;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-04 — RED-SPEC (bug Tier 0 CONFIRMADO, NÃO corrigido).
 *
 * BUG: `Modules\Vestuario\Services\DevolucaoService::registrarDevolucao` registra a devolução
 * (append-only em `vestuario_devolucoes`) e credita saldo do cliente, mas NÃO reintegra o
 * estoque — nenhuma chamada a `updateProductQuantity`/`decreaseProductQuantity`. O item físico
 * volta pra loja, o `qty_available` do sistema NÃO. Confirmado 2026-07-02:
 *   - grep no service: zero referência a estoque/VLD/qty_available;
 *   - nenhum observer/listener em `vestuario_devolucoes` compensa;
 *   - `Wave28DevolucaoServiceTest` só cobre saldo-de-crédito, nunca `qty_available`.
 *
 * CONTRATO ESPERADO (DOC-RAIZ-ESTOQUE §3 `sell_return` → ENTRA): uma devolução que tira o item
 * de circulação (estorno_dinheiro / crédito / troca por outro produto) DEVE reintegrar o saldo
 * no local da venda, pela quantidade devolvida — como o núcleo UltimatePOS faz em `addSellReturn`.
 *
 * POR QUE FICA SKIPADO (não corrige aqui): o fix mexe em ESTOQUE — é mudança Tier 0 VALOR/ESTOQUE
 * que exige DUPLA-CONFIRMAÇÃO do cálculo + apresentar antes→depois + aprovação Wagner
 * (regra mestre, memory/proibicoes.md). Este teste é o CONTRATO de não-regressão pronto:
 * quando o fix for aprovado e aplicado, REMOVER o `markTestSkipped` marcado ⬇️ RED-SPEC →
 * o teste vira green e trava o comportamento.
 *
 * @see Modules/Vestuario/Services/DevolucaoService.php::registrarDevolucao
 * @see app/Utils/TransactionUtil.php::addSellReturn (o núcleo, que reintegra — linha 6189)
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §3
 */
it('devolução Vestuario (estorno_dinheiro) DEVE reintegrar qty_available pela quantidade devolvida', function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('vestuario_devolucoes')) {
        $this->markTestSkipped('Schema UltimatePOS/Vestuario ausente — rode na lane MySQL ou CT 100.');
    }

    // ⬇️ RED-SPEC: remover esta linha quando o fix Tier 0 (Wagner-aprovado) reintegrar o estoque.
    $this->markTestSkipped('RED-SPEC — BUG Tier 0 CONFIRMADO: DevolucaoService::registrarDevolucao NÃO reintegra qty_available. Fix requer dupla-confirmação + antes→depois + aprovação Wagner (regra mestre VALOR/ESTOQUE). Ao corrigir, remover este skip → vira green.');

    // --- Contrato esperado (executa quando o skip acima for removido) ---
    $biz = EstoqueFixture::businessId();
    session(['user.business_id' => $biz]);

    $loc = EstoqueFixture::locationId($biz);
    $produto = EstoqueFixture::singleProduct($biz);
    EstoqueFixture::setStock($produto, 0, $loc, 8.0); // saldo pós-venda de 5 (já baixou)

    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    app(DevolucaoService::class)->registrarDevolucao($biz, [
        'transaction_id' => $venda['transaction_id'],
        'transaction_sell_line_id' => $venda['sell_line_id'],
        'quantidade_devolvida' => 2,
        'valor_devolvido' => 40.0,
        'tipo' => 'estorno_dinheiro',
        'aprovacao_supervisor' => true,
        'motivo' => 'Defeito — item volta pro estoque',
        'processed_by_user_id' => EstoqueFixture::userId($biz),
    ]);

    // Item devolvido volta pro estoque: 8 + 2 = 10.
    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});
