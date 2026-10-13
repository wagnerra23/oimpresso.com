<?php

declare(strict_types=1);
// Cobre UC-PIDX-15 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Estoque inicial em janela, na lista de produtos (`GET /opening-stock/dados/{id}` +
 * `POST /opening-stock/salvar-da-janela`) — protótipo `produto-blade.jsx`, `ModalEstoqueInicial`.
 *
 * O contrato é a tela Blade de estoque inicial (`opening_stock/form-part.blade.php`), que grava por
 * `OpeningStockController@save`. Os endpoints novos NÃO gravam estoque: o de salvar monta o pedido do
 * formulário Blade e entrega ao `save()`. Por isso a prova de estoque tem dois caminhos:
 *   1. a conta à mão do saldo e da linha de compra;
 *   2. um produto GÊMEO que recebe as mesmas linhas pelo caminho Blade (`/opening-stock/save`) —
 *      o retrato dos dois (saldo por local, linhas, transações) tem de sair igual.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->localA = EstoqueFixture::locationId($this->business->id, '-PEI-A-' . uniqid());
    $this->localB = EstoqueFixture::locationId($this->business->id, '-PEI-B-' . uniqid());
});

function peiEntrar($teste, array $permissoes = ['product.opening_stock', 'access_all_locations'], array $sessao = []): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(array_merge([
        'user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
        'business.date_format' => 'd/m/Y', 'business.time_format' => 24,
        'business.enable_lot_number' => 1, 'business.enable_product_expiry' => 1,
        // O `save()` lê o início do ano fiscal da sessão; em produção quem põe é o SetSessionData.
        'financial_year' => (new \App\Utils\BusinessUtil)->getCurrentFinancialYear($teste->business->id),
    ], $sessao));
}

/** Produto simples com estoque, disponível nos dois locais do cenário. */
function peiProduto($teste): object
{
    $p = EstoqueFixture::singleProduct($teste->business->id);
    foreach ([$teste->localA, $teste->localB] as $local) {
        DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
    }

    return $p;
}

function peiSaldo(object $p, int $local): ?float
{
    $r = DB::table('variation_location_details')->where('variation_id', $p->variationId(0))->where('location_id', $local)->first();

    return $r ? (float) $r->qty_available : null;
}

/** Linhas de estoque inicial do produto num local, na ordem em que foram lançadas. */
function peiLinhas(object $p, int $local): array
{
    return DB::table('purchase_lines as pl')
        ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
        ->where('t.type', 'opening_stock')->where('t.opening_stock_product_id', $p->productId)->where('t.location_id', $local)
        ->orderBy('pl.id')
        ->get(['pl.id', 'pl.quantity', 'pl.purchase_price', 'pl.purchase_price_inc_tax', 'pl.lot_number', 'pl.exp_date',
            't.transaction_date', 't.final_total', 't.status', 't.payment_status', 't.additional_notes'])
        ->all();
}

/** O que se compara entre o produto da janela e o gêmeo do Blade: saldo, linhas e transações por local. */
function peiRetrato($teste, object $p): array
{
    $out = [];
    foreach (['A' => $teste->localA, 'B' => $teste->localB] as $nome => $local) {
        $out[$nome] = [
            'saldo' => peiSaldo($p, $local),
            'linhas' => array_map(fn ($l) => [
                'quantidade' => (float) $l->quantity, 'custo' => (float) $l->purchase_price, 'custo_com_imposto' => (float) $l->purchase_price_inc_tax,
                'lote' => $l->lot_number, 'validade' => $l->exp_date, 'total' => (float) $l->final_total, 'status' => $l->status, 'pagamento' => $l->payment_status,
            ], peiLinhas($p, $local)),
        ];
    }

    return $out;
}

/** Uma linha como a JANELA manda: números com vírgula decimal e sem milhar; datas em ISO. */
function peiLinha(array $over = []): array
{
    return array_merge(['purchase_line_id' => null, 'quantidade' => '10', 'custo' => '5', 'validade' => null, 'lote' => null, 'data' => null, 'observacao' => null], $over);
}

/** Pedido da janela: `[local => [linhas]]` para o produto simples. */
function peiPedido(object $p, array $porLocal): array
{
    $locais = [];
    foreach ($porLocal as $local => $linhas) {
        $locais[] = ['location_id' => $local, 'variacoes' => [['variation_id' => $p->variationId(0), 'linhas' => $linhas]]];
    }

    return ['product_id' => $p->productId, 'locais' => $locais];
}

/** O MESMO lançamento pelo formulário Blade: `[local => [blocos]]`, números como a Blade formata. */
function peiSalvarPeloBlade($teste, object $p, array $porLocal): void
{
    $stocks = [];
    foreach ($porLocal as $local => $blocos) {
        $stocks[$local][$p->variationId(0)] = $blocos;
    }
    $teste->post('/opening-stock/save', ['product_id' => $p->productId, 'stocks' => $stocks]);
}

/** Linha da janela para uma linha que já existe (como a janela a recebeu de `/opening-stock/dados`). */
function peiExistente(object $gravada, array $over = []): array
{
    return peiLinha(array_merge(['purchase_line_id' => (int) $gravada->id, 'lote' => $gravada->lot_number], $over));
}

it('UC-PIDX-15 · lançar pela janela dá o mesmo saldo, linhas e transações que o formulário Blade', function () {
    peiEntrar($this);
    $janela = peiProduto($this);
    $gemeo = peiProduto($this);

    $r = $this->postJson('/opening-stock/salvar-da-janela', peiPedido($janela, [
        $this->localA => [peiLinha(['quantidade' => '10', 'custo' => '5'])],
        $this->localB => [peiLinha(['quantidade' => '2,5', 'custo' => '12,125', 'lote' => 'L-1'])],
    ]));
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    peiSalvarPeloBlade($this, $gemeo, [
        $this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00']],
        $this->localB => [['quantity' => '2,50', 'purchase_price' => '12,125', 'lot_number' => 'L-1']],
    ]);

    $daJanela = peiRetrato($this, $janela);
    // Caminho 1 — conta à mão: saldo 10 e 2,5; totais 10×5 = 50 e 2,5×12,125 = 30,3125; sem imposto.
    expect($daJanela['A']['saldo'])->toBe(10.0);
    expect($daJanela['B']['saldo'])->toBe(2.5);
    expect($daJanela['A']['linhas'])->toHaveCount(1);
    expect($daJanela['A']['linhas'][0])->toMatchArray(['quantidade' => 10.0, 'custo' => 5.0, 'custo_com_imposto' => 5.0, 'total' => 50.0, 'status' => 'received', 'pagamento' => 'paid']);
    expect($daJanela['B']['linhas'][0])->toMatchArray(['quantidade' => 2.5, 'custo' => 12.125, 'custo_com_imposto' => 12.125, 'total' => 30.3125, 'lote' => 'L-1']);
    // Caminho 2 — o gêmeo lançado pelo formulário Blade.
    expect($daJanela)->toEqual(peiRetrato($this, $gemeo));
});

it('UC-PIDX-15 · aumentar e diminuir uma linha existente acerta o saldo como o formulário Blade', function () {
    peiEntrar($this);
    $janela = peiProduto($this);
    $gemeo = peiProduto($this);
    foreach ([$janela, $gemeo] as $p) {
        peiSalvarPeloBlade($this, $p, [$this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00']]]);
        expect(peiSaldo($p, $this->localA))->toBe(10.0);
    }

    // 10 → 14.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($janela, [$this->localA => [peiExistente(peiLinhas($janela, $this->localA)[0], ['quantidade' => '14'])]]))->assertOk();
    peiSalvarPeloBlade($this, $gemeo, [$this->localA => [['quantity' => '14,00', 'purchase_price' => '5,00', 'purchase_line_id' => peiLinhas($gemeo, $this->localA)[0]->id]]]);
    expect(peiSaldo($janela, $this->localA))->toBe(14.0);
    expect(peiRetrato($this, $janela))->toEqual(peiRetrato($this, $gemeo));

    // 14 → 4, com custo novo.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($janela, [$this->localA => [peiExistente(peiLinhas($janela, $this->localA)[0], ['quantidade' => '4', 'custo' => '6,5'])]]))->assertOk();
    peiSalvarPeloBlade($this, $gemeo, [$this->localA => [['quantity' => '4,00', 'purchase_price' => '6,50', 'purchase_line_id' => peiLinhas($gemeo, $this->localA)[0]->id]]]);
    $retrato = peiRetrato($this, $janela);
    expect($retrato['A']['saldo'])->toBe(4.0);
    expect($retrato['A']['linhas'])->toHaveCount(1);
    expect($retrato['A']['linhas'][0])->toMatchArray(['quantidade' => 4.0, 'custo' => 6.5, 'total' => 26.0]);
    expect($retrato)->toEqual(peiRetrato($this, $gemeo));
});

it('UC-PIDX-15 · uma segunda linha no mesmo local soma ao saldo e não mexe na primeira', function () {
    peiEntrar($this);
    $janela = peiProduto($this);
    $gemeo = peiProduto($this);
    foreach ([$janela, $gemeo] as $p) {
        peiSalvarPeloBlade($this, $p, [$this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00', 'lot_number' => 'L-1']]]);
    }

    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($janela, [$this->localA => [
        peiExistente(peiLinhas($janela, $this->localA)[0]),
        peiLinha(['quantidade' => '3', 'custo' => '7', 'lote' => 'L-2']),
    ]]))->assertOk();
    peiSalvarPeloBlade($this, $gemeo, [$this->localA => [
        ['quantity' => '10,00', 'purchase_price' => '5,00', 'lot_number' => 'L-1', 'purchase_line_id' => peiLinhas($gemeo, $this->localA)[0]->id],
        ['quantity' => '3,00', 'purchase_price' => '7,00', 'lot_number' => 'L-2'],
    ]]);

    $retrato = peiRetrato($this, $janela);
    expect($retrato['A']['saldo'])->toBe(13.0);
    expect(array_column($retrato['A']['linhas'], 'quantidade'))->toBe([10.0, 3.0]);
    expect(array_column($retrato['A']['linhas'], 'lote'))->toBe(['L-1', 'L-2']);
    expect($retrato)->toEqual(peiRetrato($this, $gemeo));
});

it('UC-PIDX-15 · pedido que não traz uma linha existente do local é recusado e nada muda', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    peiSalvarPeloBlade($this, $p, [$this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00']]]);
    $antes = peiRetrato($this, $p);
    expect($antes['A']['saldo'])->toBe(10.0);

    // Só uma linha NOVA para o local A: o `save()` apagaria a linha de 10 e descontaria o saldo.
    $r = $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha(['quantidade' => '3'])]]));

    $r->assertStatus(422);
    expect($r->json('errors'))->toHaveKey('locais');
    expect(peiRetrato($this, $p))->toEqual($antes);
    // Anti-vácuo: o mesmo pedido COM a linha existente passa.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [
        peiExistente(peiLinhas($p, $this->localA)[0]), peiLinha(['quantidade' => '3']),
    ]]))->assertOk();
    expect(peiSaldo($p, $this->localA))->toBe(13.0);
});

it('UC-PIDX-15 · local que o pedido não cita não é tocado', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    peiSalvarPeloBlade($this, $p, [
        $this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00']],
        $this->localB => [['quantity' => '8,00', 'purchase_price' => '5,00']],
    ]);

    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiExistente(peiLinhas($p, $this->localA)[0], ['quantidade' => '12'])]]))->assertOk();

    expect(peiSaldo($p, $this->localA))->toBe(12.0);
    expect(peiSaldo($p, $this->localB))->toBe(8.0);
    expect(peiLinhas($p, $this->localB))->toHaveCount(1);
});

it('UC-PIDX-15 · número fora do formato é recusado e nada é lançado', function (string $campo, string $valor) {
    peiEntrar($this);
    $p = peiProduto($this);

    $r = $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha([$campo => $valor])]]));

    $r->assertStatus(422);
    expect(peiSaldo($p, $this->localA))->toBeNull();
    expect(peiLinhas($p, $this->localA))->toBe([]);
})->with([
    'quantidade com ponto de milhar' => ['quantidade', '1.500'],
    'quantidade com ponto decimal' => ['quantidade', '1.5'],
    'custo que seria lido como milhar' => ['custo', '12.125'],
    'quantidade negativa' => ['quantidade', '-3'],
    'custo vazio' => ['custo', ''],
]);

it('UC-PIDX-15 · o mesmo número com vírgula passa', function () {
    peiEntrar($this);
    $p = peiProduto($this);

    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha(['quantidade' => '1,5', 'custo' => '12,125'])]]))->assertOk();

    expect(peiSaldo($p, $this->localA))->toBe(1.5);
    expect((float) peiLinhas($p, $this->localA)[0]->purchase_price)->toBe(12.125);
});

it('UC-PIDX-15 · linha, variação ou local que não são deste produto são recusados', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    $outro = peiProduto($this);
    peiSalvarPeloBlade($this, $outro, [$this->localA => [['quantity' => '9,00', 'purchase_price' => '5,00']]]);
    $linhaDoOutro = peiLinhas($outro, $this->localA)[0];
    $localSemProduto = EstoqueFixture::locationId($this->business->id, '-PEI-C-' . uniqid());
    $antesDoOutro = peiRetrato($this, $outro);

    // Linha de estoque inicial de OUTRO produto.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha(['purchase_line_id' => (int) $linhaDoOutro->id, 'quantidade' => '1'])]]))->assertStatus(422);
    // Variação de outro produto.
    $this->postJson('/opening-stock/salvar-da-janela', ['product_id' => $p->productId, 'locais' => [
        ['location_id' => $this->localA, 'variacoes' => [['variation_id' => $outro->variationId(0), 'linhas' => [peiLinha()]]]],
    ]])->assertStatus(422);
    // Local em que o produto não está disponível.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$localSemProduto => [peiLinha()]]))->assertStatus(422);

    expect(peiSaldo($p, $this->localA))->toBeNull();
    expect(peiSaldo($p, $localSemProduto))->toBeNull();
    expect(peiRetrato($this, $outro))->toEqual($antesDoOutro);
    // Anti-vácuo: o pedido legítimo do mesmo produto passa.
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha()]]))->assertOk();
    expect(peiSaldo($p, $this->localA))->toBe(10.0);
});

it('UC-PIDX-15 · produto de outra empresa não abre nem recebe estoque pela janela', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    peiEntrar($this);
    $alheio = EstoqueFixture::singleProduct($outroBiz);
    $meu = peiProduto($this);

    $this->getJson('/opening-stock/dados/' . $alheio->productId)->assertStatus(404);
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($alheio, [$this->localA => [peiLinha()]]))->assertStatus(404);
    expect(DB::table('variation_location_details')->where('product_id', $alheio->productId)->count())->toBe(0);
    // Anti-vácuo: o meu produto abre.
    $this->getJson('/opening-stock/dados/' . $meu->productId)->assertOk();
});

it('UC-PIDX-15 · sem product.opening_stock a janela não abre nem salva; produto sem controle de estoque não abre', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    $semEstoque = EstoqueFixture::singleProduct($this->business->id, false);
    $this->getJson('/opening-stock/dados/' . $semEstoque->productId)->assertStatus(404);

    peiEntrar($this, ['product.view', 'access_all_locations']);
    $this->getJson('/opening-stock/dados/' . $p->productId)->assertStatus(403);
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha()]]))->assertStatus(403);
    expect(peiSaldo($p, $this->localA))->toBeNull();
});

it('UC-PIDX-15 · a janela recebe as linhas que a tela Blade mostra, por local', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    peiSalvarPeloBlade($this, $p, [
        $this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00', 'lot_number' => 'L-1'], ['quantity' => '3,00', 'purchase_price' => '7,00']],
    ]);

    $dados = $this->getJson('/opening-stock/dados/' . $p->productId);
    $dados->assertOk();
    $locais = collect($dados->json('locais'))->keyBy('id');
    // Os dois locais do produto, e só eles.
    expect($locais->keys()->sort()->values()->all())->toBe(collect([$this->localA, $this->localB])->sort()->values()->all());
    $linhasA = $locais[$this->localA]['variacoes'][0]['linhas'];
    expect($locais[$this->localB]['variacoes'][0]['linhas'])->toBe([]);

    // A tela Blade, para o mesmo produto: as mesmas linhas, na mesma ordem.
    $blade = $this->get('/opening-stock/add/' . $p->productId);
    $blade->assertOk();
    $daBlade = $blade->viewData('purchases')[$this->localA][$p->variationId(0)];
    expect($linhasA)->toHaveCount(2);
    expect($daBlade)->toHaveCount(2);
    foreach ($linhasA as $i => $linha) {
        expect($linha['purchase_line_id'])->toBe((int) $daBlade[$i]['purchase_line_id']);
        expect((float) $linha['quantidade'])->toBe((float) $daBlade[$i]['quantity']);
        expect((float) $linha['custo'])->toBe((float) $daBlade[$i]['purchase_price']);
        expect($linha['lote'])->toBe($daBlade[$i]['lot_number']);
    }
    expect([(float) $linhasA[0]['quantidade'], (float) $linhasA[1]['quantidade']])->toBe([10.0, 3.0]);
});

it('UC-PIDX-15 · data e validade: grava o que foi informado, e salvar de novo sem mexer não muda a data', function () {
    peiEntrar($this);
    $p = peiProduto($this);

    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [
        peiLinha(['data' => '2026-03-10T14:30', 'validade' => '2027-01-31', 'observacao' => 'contagem de março']),
    ]]))->assertOk();
    $linha = peiLinhas($p, $this->localA)[0];
    expect((string) $linha->transaction_date)->toBe('2026-03-10 14:30:00');
    expect(substr((string) $linha->exp_date, 0, 10))->toBe('2027-01-31');
    expect($linha->additional_notes)->toBe('contagem de março');

    // A janela reabre, recebe a linha e salva de novo sem o usuário mexer na data.
    $recebida = collect($this->getJson('/opening-stock/dados/' . $p->productId)->json('locais'))->keyBy('id')[$this->localA]['variacoes'][0]['linhas'][0];
    expect($recebida['data'])->toBe('2026-03-10T14:30');
    expect($recebida['validade'])->toBe('2027-01-31');
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha([
        'purchase_line_id' => $recebida['purchase_line_id'], 'quantidade' => '10', 'custo' => '5',
        'data' => $recebida['data'], 'validade' => $recebida['validade'], 'observacao' => $recebida['observacao'],
    ])]]))->assertOk();

    $depois = peiLinhas($p, $this->localA)[0];
    expect((string) $depois->transaction_date)->toBe('2026-03-10 14:30:00');
    expect(substr((string) $depois->exp_date, 0, 10))->toBe('2027-01-31');
});

it('UC-PIDX-15 · com lote desligado na empresa, a linha existente mantém o lote que já tinha', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [peiLinha(['lote' => 'L-9'])]]))->assertOk();
    expect(peiLinhas($p, $this->localA)[0]->lot_number)->toBe('L-9');

    // A empresa desliga o lote; a janela não mostra mais o campo e não manda lote.
    session(['business.enable_lot_number' => 0]);
    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($p, [$this->localA => [
        peiLinha(['purchase_line_id' => (int) peiLinhas($p, $this->localA)[0]->id, 'quantidade' => '12']),
    ]]))->assertOk();

    $linha = peiLinhas($p, $this->localA)[0];
    expect((float) $linha->quantity)->toBe(12.0);
    expect($linha->lot_number)->toBe('L-9');
});

it('UC-PIDX-15 · campo fora do pedido da janela não chega à gravação', function () {
    peiEntrar($this);
    $p = peiProduto($this);
    $pedido = peiPedido($p, [$this->localA => [peiLinha()]]);
    // Um `stocks` cru (o formato do formulário Blade) enfiado junto, apontando para o local B.
    $pedido['stocks'] = [$this->localB => [$p->variationId(0) => [['quantity' => '99', 'purchase_price' => '1']]]];

    $this->postJson('/opening-stock/salvar-da-janela', $pedido)->assertOk();

    expect(peiSaldo($p, $this->localA))->toBe(10.0);
    expect(peiSaldo($p, $this->localB))->toBeNull();
});

it('UC-PIDX-15 · zerar uma linha existente: a janela faz o mesmo que o formulário Blade', function () {
    // Este caso afirma SÓ a igualdade com o caminho Blade — o contrato da janela. Ele não afirma
    // qual deve ser o saldo depois de zerar: pela leitura do `save()`, quando a quantidade nova é
    // zero o saldo NÃO é descontado (`if ($qty_remaining != 0)`), o que é um achado do caminho
    // antigo, registrado no backlog do casos.md e ainda sem decisão. Não se escolhe remédio aqui.
    peiEntrar($this);
    $janela = peiProduto($this);
    $gemeo = peiProduto($this);
    foreach ([$janela, $gemeo] as $p) {
        peiSalvarPeloBlade($this, $p, [$this->localA => [['quantity' => '10,00', 'purchase_price' => '5,00']]]);
        expect(peiSaldo($p, $this->localA))->toBe(10.0);
    }

    $this->postJson('/opening-stock/salvar-da-janela', peiPedido($janela, [$this->localA => [peiExistente(peiLinhas($janela, $this->localA)[0], ['quantidade' => '0'])]]))->assertOk();
    peiSalvarPeloBlade($this, $gemeo, [$this->localA => [['quantity' => '0,00', 'purchase_price' => '5,00', 'purchase_line_id' => peiLinhas($gemeo, $this->localA)[0]->id]]]);

    expect(peiRetrato($this, $janela))->toEqual(peiRetrato($this, $gemeo));
});
