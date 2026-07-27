<?php

declare(strict_types=1);
// Cobre UC-PFIX-02, UC-PFIX-03
// (memory/requisitos/Produto/_telas/ajuste-estoque-relatorio.casos.md).
// O UC-PFIX-01 (parser locale-safe) é coberto pelo irmão EstoqueFixMismatchNumUfTest.php,
// que já roda nesta mesma lane — não se duplica assert (proibicoes §5 2026-07-09).

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento do BOTÃO "Fix" DO RELATÓRIO DE ESTOQUE
 * (`GET /reports/adjust-product-stock?location_id&variation_id&stock`
 *  → `ReportController@adjustProductStock` → `ProductUtil::fixVariationStockMisMatch`).
 *
 * ⚠️ FLUXO SEM TELA REACT. O único emissor é a Blade
 *    `resources/views/report/product_stock_details.blade.php:167` — um `<a href>` que
 *    monta a URL com `stock={{$row->total_stock_calculated}}`. Varredura contada
 *    (2026-07-27, sha 16606e35c4): `adjustProductStock|adjust-product-stock` aparece em
 *    **1 rota + 1 Blade**, e em **0** arquivos de `resources/js/`.
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada (ADR 0351):
 *   1. CANON  — `US-PROD-028` (SPEC do Produto, `status: done`, PR #4636) + `CU-PROD-10`
 *               `[T0]` do SDD §6.1 + DOC-RAIZ-ESTOQUE §7 INV-6 (saldo endereçado pelo par
 *               variação × local) + REGRA MESTRE (proibicoes.md Tier 0).
 *   2. BLADE  — `report/product_stock_details.blade.php` (o caller real e a semântica do
 *               botão: "reconciliar o saldo DESTA linha", uma linha = um par variação×local).
 *   3. DELPHI — `AR-PROD-052` `[V0]` (botão **Verificar** — recalcula/confere a
 *               disponibilidade sob demanda) e `AR-PROD-051` `[V0]` (Disponível é saldo
 *               POR LOCAL) da ANTI-REGRESSAO-cadastro-produto-legacy.md. No legado o
 *               "Verificar" confere o saldo do local exibido, não o do produto inteiro.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A `US-PROD-028` está `status: done` e o painel `_STATUS-GENERATED.md` a listava como
 * **"entregue sem contrato"**: o `EstoqueFixMismatchNumUfTest` prova o eixo NUMÉRICO
 * (num_uf), mas nenhum teste cobria os dois eixos que a própria US declarou fora do
 * escopo do fix e que continuam vivos — o TENANT e o ALCANCE da escrita.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato, NÃO do
 *    `fixVariationStockMisMatch()`. Eixo ESTOQUE → o fix é decisão [W] sob a REGRA MESTRE.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business
 *    seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

// =============================================================================
// UC-PFIX-02 — `CU-PROD-10` `[T0]` + ADR 0093 + o "escopo honesto" da própria US-PROD-028
//   (*"endpoint GET com `stock` arbitrário na query → tampering grava qualquer valor"*).
//   Se os parâmetros vêm da URL, o guard de tenant é a ÚNICA coisa entre um operador
//   qualquer com `report.stock_details` e o saldo do vizinho de servidor.
//
//   ⚠️ O que este UC NÃO afirma: qual deve ser a RESPOSTA quando o par é alheio. Medido:
//   o método sai do `if (! empty($vld))` e logo abaixo dereferencia `$vld->id` na query
//   de deduplicação — com `$vld` nulo. O desfecho HTTP disso é divergência aberta
//   (§Backlog do casos.md), e escolher 404 × 422 × silêncio seria escolher remédio
//   (proibicoes §5 2026-07-15). O invariante Tier 0 — "o saldo alheio não muda" — não é.
// =============================================================================

it('UC-PFIX-02 · o Fix não altera saldo de variação de outro business (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    // Estado conhecido do OUTRO tenant (semeado por fora do mutador sob teste).
    $localAlheio = EstoqueFixture::locationId($outroBizId);
    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);
    EstoqueFixture::setStock($produtoAlheio, 0, $localAlheio, 42.0);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (parte 1): o mutador FUNCIONA quando o par é meu — senão
    // "o saldo alheio não mudou" seria verdade só porque a função não faz nada.
    $meuLocal = EstoqueFixture::locationId($this->biz);
    $meuProduto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($meuProduto, 0, $meuLocal, 1.0);

    (new ProductUtil)->fixVariationStockMisMatch(
        $this->biz,
        $meuProduto->variations[0]['variation_id'],
        $meuLocal,
        '8'
    );

    expect(EstoqueFixture::currentStock($meuProduto, 0, $meuLocal))->toBe(
        8.0,
        'PRÉ-CONDIÇÃO do UC-PFIX-02: o Fix não gravou nem no MEU par — a fase cross-tenant '
        . 'mediria não-execução e chamaria de isolamento.'
    );

    // TAMPERING: minha sessão (business = $this->biz) apontando pro par do OUTRO tenant.
    // A chamada pode estourar antes de terminar (o método dereferencia `$vld->id` mesmo
    // quando o par não foi encontrado). O que se afirma aqui é só o EFEITO no banco —
    // por isso o Throwable é registrado e não vira veredito.
    $excecao = null;
    try {
        (new ProductUtil)->fixVariationStockMisMatch(
            $this->biz,
            $produtoAlheio->variations[0]['variation_id'],
            $localAlheio,
            '999'
        );
    } catch (\Throwable $e) {
        $excecao = $e;
    }

    // O CONTRATO: o saldo do outro tenant permanece exatamente o que era.
    expect(EstoqueFixture::currentStock($produtoAlheio, 0, $localAlheio))->toBe(
        42.0,
        'O "Fix" do relatório sobrescreveu o saldo de OUTRO business'
        . ($excecao ? ' (e ainda lançou ' . get_class($excecao) . ')' : '')
        . '. Os três parâmetros vêm da querystring de um GET — quem sabe montar a URL escreve.'
    );
});

// =============================================================================
// UC-PFIX-03 — DOC-RAIZ-ESTOQUE §7 INV-6 (o saldo é endereçado pelo PAR variação × local)
//   + `AR-PROD-051`/`AR-PROD-052` `[V0]` (no legado o "Verificar" confere a disponibilidade
//   DAQUELE local) + Blade `product_stock_details` (cada linha = um par, e o botão está
//   na linha). Reconciliar um depósito não pode mexer no saldo do outro.
//
//   Por que este UC não é decorativo: além do UPDATE, o método roda um DELETE de linhas
//   "duplicadas" do mesmo par. Se algum dia o filtro de local sair dessa segunda query
//   (ou o índice mudar), o botão passa a APAGAR o saldo dos outros locais — em silêncio,
//   dentro de um GET, sem confirmação.
// =============================================================================

it('UC-PFIX-03 · o Fix reconcilia só o local da linha — o saldo dos outros locais não muda', function () {
    $localA = EstoqueFixture::locationId($this->biz);
    $localB = EstoqueFixture::locationId($this->biz, '-UC-PFIX-03');

    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $localA, 5.0);
    EstoqueFixture::setStock($produto, 0, $localB, 30.0);

    (new ProductUtil)->fixVariationStockMisMatch(
        $this->biz,
        $produto->variations[0]['variation_id'],
        $localA,
        '17'
    );

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a reconciliação do local A aconteceu de fato.
    expect(EstoqueFixture::currentStock($produto, 0, $localA))->toBe(
        17.0,
        'PRÉ-CONDIÇÃO do UC-PFIX-03: o Fix não reconciliou o local alvo — o assert do local '
        . 'vizinho mediria vácuo.'
    );

    // O CONTRATO: o depósito vizinho segue intacto (nem sobrescrito, nem apagado).
    expect(EstoqueFixture::currentStock($produto, 0, $localB))->toBe(
        30.0,
        'Reconciliar o saldo de UM local alterou o saldo de OUTRO local do mesmo produto. '
        . 'No legado o "Verificar" (AR-PROD-052) confere a disponibilidade daquele local; '
        . 'aqui um clique de conferência viraria movimentação não-solicitada (REGRA MESTRE).'
    );
});
