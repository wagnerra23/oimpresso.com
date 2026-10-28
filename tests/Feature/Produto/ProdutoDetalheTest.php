<?php

declare(strict_types=1);
// Cobre UC-PIDX-09, UC-PIDX-11, UC-PIDX-12, UC-PIDX-20, UC-PIDX-22, UC-PIDX-25 e UC-PIDX-33 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Painel de detalhe do produto na lista (`Produto/Index`, prop `detalhe`, `?ver={id}`) — protótipo
 * `prototipo-ui/cowork/Felipe/produto-blade.jsx`, `DetalheDrawer`, com o conteúdo e os gates da
 * janela "Ver" da lista Blade (`ProductController::view`).
 *
 * Os valores são comparados com o que está gravado na variação (e não com o próprio painel), e o
 * estoque por local com as linhas da tela de Relatórios → Estoque.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function pdtEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** A prop `detalhe` do produto, como o painel pede (partial reload com `ver`). */
function pdtDetalhe($teste, int $id): mixed
{
    $r = $teste->get('/products?ver=' . $id, $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'detalhe',
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect($r->json('props'))->toHaveKey('detalhe');

    return $r->json('props.detalhe');
}

function pdtProdutoComPrecos(int $businessId): object
{
    $p = EstoqueFixture::singleProduct($businessId);
    DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update([
        'default_purchase_price' => 10, 'dpp_inc_tax' => 11, 'profit_percent' => 50,
        'default_sell_price' => 15, 'sell_price_inc_tax' => 16.5,
    ]);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '61046200', 'cest' => '2806300']);

    return $p;
}

it('UC-PIDX-09 · com todas as permissões o painel traz os preços gravados na variação e os dados fiscais', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price', 'access_default_selling_price']);
    $p = pdtProdutoComPrecos($this->business->id);

    $d = pdtDetalhe($this, $p->productId);

    expect($d['id'])->toBe($p->productId);
    $v = $d['variacoes'][0];
    expect([$v['compra_exc'], $v['compra_inc'], $v['lucro_percentual'], $v['venda_exc'], $v['venda_inc']])
        ->toEqual([10.0, 11.0, 50.0, 15.0, 16.5]);
    expect($d['fiscal']['ncm'])->toBe('61046200');
    expect($d['fiscal']['cest'])->toBe('2806300');
});

it('UC-PIDX-09 · sem as permissões de preço as chaves de custo e venda não vêm (como na janela "Ver" da Blade)', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $p = pdtProdutoComPrecos($this->business->id);

    $v = pdtDetalhe($this, $p->productId)['variacoes'][0];

    // Anti-vácuo: a variação veio — a ausência das chaves é do gate, não de linha faltando.
    expect($v)->toHaveKey('sku');
    foreach (['compra_exc', 'compra_inc', 'lucro_percentual', 'venda_exc', 'venda_inc'] as $chave) {
        expect($v)->not->toHaveKey($chave);
    }
});

it('UC-PIDX-09 · o estoque por local é o da tela de Relatórios → Estoque e só vem com stock_report.view', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-PDT-' . uniqid());
    pdtEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
    EstoqueFixture::setStock($p, 0, $local, 6);

    $nomeLocal = (string) DB::table('business_locations')->where('id', $local)->value('name');
    $doLocal = collect(pdtDetalhe($this, $p->productId)['estoque'])->where('local', $nomeLocal)->values();
    expect($doLocal)->toHaveCount(1);

    $r = $this->get('/reports/stock-report?' . http_build_query(['tela' => 'nova', 'location_id' => $local]), $this->inertia);
    $r->assertOk();
    $daTela = collect($r->json('props.linhas'))->where('sku', $doLocal[0]['sku'])->values();
    expect($daTela)->toHaveCount(1);

    expect($doLocal[0])->toEqual($daTela[0]);
    expect(abs($doLocal[0]['estoque'] - 6))->toBeLessThan(0.005);

    pdtEntrar($this, ['product.view', 'access_all_locations']);
    expect(pdtDetalhe($this, $p->productId)['estoque'])->toBeNull();
});

it('UC-PIDX-09 · produto de outra empresa não abre no painel', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    pdtEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price', 'access_default_selling_price']);
    $alheio = EstoqueFixture::singleProduct($outro);
    $meu = EstoqueFixture::singleProduct($this->business->id);

    // Anti-vácuo: o próprio produto abre — o null do alheio não é painel quebrado.
    expect(pdtDetalhe($this, $meu->productId))->not->toBeNull();
    expect(pdtDetalhe($this, $alheio->productId))->toBeNull();
});

it('UC-PIDX-09 · aberto pela URL (?ver=) o painel chega como prop deferida da página', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);

    $r = $this->get('/products?ver=' . $p->productId, $this->inertia);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect(collect($r->json('deferredProps'))->flatten()->all())->toContain('detalhe');
});

it('UC-PIDX-11 · a consulta não oferece compra, nem a quem pode criar compra', function () {
    // Discriminante: o usuário TEM purchase.create, que era o que ligava o "Comprar" até 2026-10-26.
    pdtEntrar($this, ['product.view', 'access_all_locations', 'purchase.create']);
    $r = $this->get('/products', $this->inertia);
    $r->assertOk();
    // Anti-vácuo: as permissões vieram — a ausência é da decisão, não de prop faltando.
    expect($r->json('props.permissions'))->toHaveKey('view');
    expect($r->json('props.permissions'))->not->toHaveKey('comprar');
});

/** Compra recebida do produto num local — é dela que sai o "valor pelo custo" do relatório de estoque. */
function pdtCompraRecebida(int $businessId, object $p, int $local, int $criador, float $qtd, float $custo): void
{
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'paid',
        'transaction_date' => '2099-01-01 10:00:00', 'final_total' => $qtd * $custo, 'total_before_tax' => $qtd * $custo,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'PDT-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $qtd,
        'purchase_price' => $custo, 'purchase_price_inc_tax' => $custo, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto com estoque e compra em DOIS locais: A = 7 un. a 12; B = 3 un. a 30; preço de venda 20. */
function pdtProdutoEmDoisLocais($teste): object
{
    $p = EstoqueFixture::singleProduct($teste->business->id);
    DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update(['sell_price_inc_tax' => 20]);
    foreach ([['A', 7, 12], ['B', 3, 30]] as [$nome, $qtd, $custo]) {
        $local = EstoqueFixture::locationId($teste->business->id, '-PDT-' . $nome . '-' . uniqid());
        DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
        EstoqueFixture::setStock($p, 0, $local, $qtd);
        pdtCompraRecebida($teste->business->id, $p, $local, $teste->user->id, $qtd, $custo);
    }

    return $p;
}

/** Soma, linha a linha, do JSON do DataTable de Relatórios → Estoque para o produto (o código da Blade). */
function pdtSomaDataTable($teste, int $productId): array
{
    $colunas = [];
    foreach ([['sku', 'variations.sub_sku'], ['stock', 'stock'], ['stock_price', 'stock_price'], ['stock_value_by_sale_price', 'stock_value_by_sale_price'], ['potential_profit', 'potential_profit'], ['total_sold', 'total_sold']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $teste->get('/reports/stock-report?' . http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
        'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'product_id' => $productId]),
        ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $orig = fn ($v) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $v, $m) ? $m[1] : 0);
    $soma = ['linhas' => 0, 'estoque' => 0.0, 'valor_compra' => 0.0, 'valor_venda' => 0.0, 'lucro_potencial' => 0.0, 'vendido' => 0.0];
    foreach ($dt->json('data') as $l) {
        $soma['linhas']++;
        $soma['estoque'] += $orig($l['stock'] ?? '');
        $soma['valor_compra'] += $orig($l['stock_price'] ?? '');
        $soma['valor_venda'] += $orig($l['stock_value_by_sale_price'] ?? '');
        $soma['lucro_potencial'] += $orig($l['potential_profit'] ?? '');
        $soma['vendido'] += $orig($l['total_sold'] ?? '');
    }

    return $soma;
}

/** Venda do produto num local, com status e quantidade devolvida. */
function pdtVenda(int $businessId, object $p, int $local, int $criador, string $status, float $qtd, float $devolvido): void
{
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
        'transaction_date' => '2099-01-02 10:00:00', 'final_total' => $qtd * 20, 'total_before_tax' => $qtd * 20,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'PDT-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines')->insert([
        'transaction_id' => $venda, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $qtd,
        'quantity_returned' => $devolvido, 'unit_price' => 20, 'unit_price_inc_tax' => 20, 'unit_price_before_discount' => 20,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PIDX-33 · o vendido de cada local é o "Total vendido" do relatório de estoque: vendas finalizadas menos devoluções', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    $locais = [];
    $ids = [];
    foreach (['A' => 7, 'B' => 3] as $nome => $qtd) {
        $local = EstoqueFixture::locationId($this->business->id, '-PDT-V' . $nome . '-' . uniqid());
        DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
        EstoqueFixture::setStock($p, 0, $local, $qtd);
        $locais[$nome] = (string) DB::table('business_locations')->where('id', $local)->value('name');
        $ids[$nome] = $local;
    }
    // No local A: uma venda finalizada de 5 com 1 devolvido, e um rascunho de 2 que não é venda.
    pdtVenda($this->business->id, $p, $ids['A'], $this->user->id, 'final', 5, 1);
    pdtVenda($this->business->id, $p, $ids['A'], $this->user->id, 'draft', 2, 0);

    $d = pdtDetalhe($this, $p->productId);
    $porLocal = collect($d['estoque'])->mapWithKeys(fn ($l) => [$l['local'] => $l['vendido']]);

    // Anti-vácuo: os dois locais vieram — o zero do B não é linha faltando.
    expect($porLocal)->toHaveCount(2);
    // Caminho 1 — conta à mão: A = 5 − 1 devolvido (o rascunho fica fora); B não vendeu.
    expect(abs($porLocal[$locais['A']] - 4))->toBeLessThan(0.005);
    expect(abs($porLocal[$locais['B']] - 0))->toBeLessThan(0.005);
    // Caminho 2 — o código da Blade (DataTable de Relatórios → Estoque), somado linha a linha.
    $dt = pdtSomaDataTable($this, $p->productId);
    expect($dt['linhas'])->toBe(2);
    expect(abs($dt['vendido'] - $porLocal->sum()))->toBeLessThan(0.005);
});

it('UC-PIDX-12 · os indicadores da aba Estoque são a soma das linhas do relatório de estoque do produto', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'view_product_stock_value', 'access_default_selling_price']);
    $p = pdtProdutoEmDoisLocais($this);

    $d = pdtDetalhe($this, $p->productId);
    $t = $d['estoque_totais'];

    // Anti-vácuo: os dois locais do cenário viraram duas linhas — somar lista vazia provaria nada.
    expect($d['estoque'])->toHaveCount(2);
    expect($t['linhas'])->toBe(2);
    // Caminho 1 — conta à mão: estoque 7 + 3; custo 7×12 + 3×30; venda (7 + 3)×20; lucro = venda − custo.
    expect(abs($t['estoque'] - 10))->toBeLessThan(0.005);
    expect(abs($t['valor_compra'] - 174))->toBeLessThan(0.005);
    expect(abs($t['valor_venda'] - 200))->toBeLessThan(0.005);
    expect(abs($t['lucro_potencial'] - 26))->toBeLessThan(0.005);
    // Caminho 2 — o código da Blade (DataTable de Relatórios → Estoque), somado linha a linha.
    $dt = pdtSomaDataTable($this, $p->productId);
    expect($dt['linhas'])->toBe(2);
    foreach (['estoque', 'valor_compra', 'valor_venda', 'lucro_potencial'] as $c) {
        expect(abs($t[$c] - $dt[$c]))->toBeLessThan(0.005);
    }
    // E cada indicador é a soma das linhas que o próprio painel lista logo abaixo.
    foreach (['estoque', 'valor_compra', 'valor_venda', 'lucro_potencial'] as $c) {
        expect(abs($t[$c] - collect($d['estoque'])->sum($c)))->toBeLessThan(0.005);
    }
});

it('UC-PIDX-12 · sem view_product_stock_value os valores vêm null (não zero); sem stock_report.view os indicadores não vêm', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view']);
    $p = pdtProdutoEmDoisLocais($this);

    $t = pdtDetalhe($this, $p->productId)['estoque_totais'];
    // Anti-vácuo: o total veio e a quantidade está certa — o null é do gate, não de total faltando.
    expect(abs($t['estoque'] - 10))->toBeLessThan(0.005);
    expect($t['valor_compra'])->toBeNull();
    expect($t['valor_venda'])->toBeNull();
    expect($t['lucro_potencial'])->toBeNull();

    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $d = pdtDetalhe($this, $p->productId);
    expect($d)->toHaveKey('estoque_totais');
    expect($d['estoque_totais'])->toBeNull();
});

/**
 * Produto composto com 2 itens de preços conhecidos: o item A entra 2 vezes (custo 10) e o item B
 * 3 vezes (custo 7,50). O próprio composto tem preço de venda 50 sem imposto e 55 com imposto.
 *
 * @return array{0: \Tests\Support\EstoqueProduto, 1: \Tests\Support\EstoqueProduto, 2: \Tests\Support\EstoqueProduto}
 */
function pdtComposto(int $businessId): array
{
    $itemA = EstoqueFixture::singleProduct($businessId);
    $itemB = EstoqueFixture::singleProduct($businessId);
    DB::table('variations')->where('id', $itemA->variationId())->update([
        'default_purchase_price' => 10, 'dpp_inc_tax' => 11, 'profit_percent' => 40,
        'default_sell_price' => 14, 'sell_price_inc_tax' => 15.4,
    ]);
    DB::table('variations')->where('id', $itemB->variationId())->update([
        'default_purchase_price' => 7.5, 'dpp_inc_tax' => 8.25, 'profit_percent' => 20,
        'default_sell_price' => 9, 'sell_price_inc_tax' => 9.9,
    ]);
    $kit = EstoqueFixture::singleProduct($businessId, false);
    DB::table('products')->where('id', $kit->productId)->update(['type' => 'combo']);
    DB::table('variations')->where('id', $kit->variationId())->update([
        'combo_variations' => json_encode([
            ['variation_id' => $itemA->variationId(), 'quantity' => 2, 'unit_id' => $itemA->unitId],
            ['variation_id' => $itemB->variationId(), 'quantity' => 3, 'unit_id' => $itemB->unitId],
        ]),
        'default_purchase_price' => 42.5, 'dpp_inc_tax' => 46.75, 'profit_percent' => 17.65,
        'default_sell_price' => 50, 'sell_price_inc_tax' => 55,
    ]);

    return [$kit, $itemA, $itemB];
}

it('UC-PIDX-20 · cada item da composição traz as colunas de preço da Blade e o "Total líquido" é a soma dos totais', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price', 'access_default_selling_price']);
    [$kit] = pdtComposto($this->business->id);

    $d = pdtDetalhe($this, $kit->productId);

    // Anti-vácuo: os dois itens chegaram.
    expect($d['composicao'])->toHaveCount(2);
    $campos = ['compra_exc', 'compra_inc', 'lucro_percentual', 'venda_exc', 'venda_inc', 'total_exc'];
    $item = fn (array $i) => array_map(fn ($c) => $i[$c], $campos);
    // Os preços gravados em cada variação; total = custo × quantidade (unidade sem subunidade, multiplicador 1).
    expect($item($d['composicao'][0]))->toEqual([10.0, 11.0, 40.0, 14.0, 15.4, 20.0]);
    expect($item($d['composicao'][1]))->toEqual([7.5, 8.25, 20.0, 9.0, 9.9, 22.5]);

    // Caminho 1 — conta à mão: 10 × 2 + 7,50 × 3.
    expect(abs($d['composicao_total_exc'] - 42.5))->toBeLessThan(0.005);
    // Caminho 2 — o código da Blade: a janela "Ver" (`combo_product_details`) imprime o total de cada
    // item (custo × quantidade × multiplicador); a soma dessas linhas é o total líquido.
    $html = $this->get('/products/view/' . $kit->productId)->assertOk()->getContent();
    preg_match_all(
        '/data-is_quantity=true\s*>\s*([\d.]+)\s*<\/span>[^<]*<\/td>\s*<td>\s*<span class="display_currency" data-currency_symbol="true">\s*([\d.]+)\s*<\/span>/',
        $html,
        $m
    );
    expect($m[2])->toHaveCount(2); // anti-vácuo: as duas linhas da Blade foram lidas
    expect(abs($d['composicao_total_exc'] - array_sum(array_map('floatval', $m[2]))))->toBeLessThan(0.005);
    // E é a soma dos totais que o próprio painel lista.
    expect(abs($d['composicao_total_exc'] - collect($d['composicao'])->sum('total_exc')))->toBeLessThan(0.005);

    // Preço de venda padrão da composição: o da variação do composto, o mesmo que a Blade imprime.
    expect([$d['variacoes'][0]['venda_exc'], $d['variacoes'][0]['venda_inc']])->toEqual([50.0, 55.0]);
    // O "Preço de venda padrão" vem logo depois da tabela da composição (a última linha de item).
    $depoisDaTabela = substr($html, (int) strrpos($html, 'data-is_quantity=true'));
    expect(preg_match('/<\/strong>\s*<span class="display_currency" data-currency_symbol="true">\s*([\d.]+)\s*<\/span>/', $depoisDaTabela, $preco))->toBe(1);
    expect((float) $preco[1])->toBe(55.0);
});

it('UC-PIDX-20 · sem permissão de custo o total não vem (null, não zero) e cada item segue os gates das variações', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'access_default_selling_price']);
    [$kit] = pdtComposto($this->business->id);

    $d = pdtDetalhe($this, $kit->productId);

    expect($d['composicao'])->toHaveCount(2); // anti-vácuo
    expect($d['composicao_total_exc'])->toBeNull();
    foreach ($d['composicao'] as $i) {
        foreach (['compra_exc', 'compra_inc', 'lucro_percentual', 'total_exc'] as $chave) {
            expect($i)->not->toHaveKey($chave);
        }
        expect($i)->toHaveKey('venda_exc');
        expect($i)->toHaveKey('venda_inc');
    }

    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $d = pdtDetalhe($this, $kit->productId);
    expect($d['composicao'])->toHaveCount(2); // anti-vácuo
    foreach ($d['composicao'] as $i) {
        foreach (['compra_exc', 'compra_inc', 'lucro_percentual', 'venda_exc', 'venda_inc', 'total_exc'] as $chave) {
            expect($i)->not->toHaveKey($chave);
        }
    }
});

it('UC-PIDX-20 · produto que não é composto não tem total da composição', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $p = pdtProdutoComPrecos($this->business->id);

    $d = pdtDetalhe($this, $p->productId);

    expect($d)->toHaveKey('composicao_total_exc');
    expect($d['composicao_total_exc'])->toBeNull();
    expect($d['composicao'])->toBe([]);
});

it('UC-PIDX-20 · variações e itens trazem as imagens da variação, só as da própria empresa', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    [$kit, $itemA] = pdtComposto($this->business->id);
    $midia = fn (int $empresa, int $variacao, string $arquivo) => DB::table('media')->insert([
        'business_id' => $empresa, 'file_name' => $arquivo,
        'model_type' => \App\Variation::class, 'model_id' => $variacao,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $midia($this->business->id, $itemA->variationId(), '1700000000_pdt_item.jpg');
    $midia($this->business->id + 1000000, $itemA->variationId(), '1700000000_pdt_outra_empresa.jpg');
    $midia($this->business->id, $kit->variationId(), '1700000000_pdt_kit.jpg');

    $d = pdtDetalhe($this, $kit->productId);

    $nomes = fn (array $urls) => array_map(fn ($u) => basename((string) $u), $urls);
    expect($nomes($d['composicao'][0]['imagens']))->toBe(['1700000000_pdt_item.jpg']);
    expect($d['composicao'][1]['imagens'])->toBe([]);
    expect($nomes($d['variacoes'][0]['imagens']))->toBe(['1700000000_pdt_kit.jpg']);
    // A URL é a mesma da Blade (`Media::display_url`).
    expect($d['composicao'][0]['imagens'][0])->toEndWith('/uploads/media/1700000000_pdt_item.jpg');
});

it('UC-PIDX-22 · produto sem foto chega ao painel com imagem null, não com a imagem genérica', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $semFoto = EstoqueFixture::singleProduct($this->business->id);
    $comFoto = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $semFoto->productId)->update(['image' => null]);
    DB::table('products')->where('id', $comFoto->productId)->update(['image' => '1700000000_pdt_foto.jpg']);

    expect(pdtDetalhe($this, $semFoto->productId)['imagem'])->toBeNull();
    // Com foto, a mesma URL que a Blade usa (acessor `image_url`).
    expect(pdtDetalhe($this, $comFoto->productId)['imagem'])->toEndWith('/uploads/img/1700000000_pdt_foto.jpg');
});

/**
 * Liga ou desliga só o `fiscal_module` no pacote da empresa — o resto do `ModuleUtil` responde
 * como sempre (uma instância nova, sem mock), pra não mudar o que a tela faz com os outros módulos.
 */
function pdtPacoteNfe(bool $ligado): void
{
    test()->partialMock(\App\Utils\ModuleUtil::class, function ($m) use ($ligado) {
        $m->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(
            fn ($biz, $modulo, $callback = null) => $modulo === 'fiscal_module'
                ? $ligado
                : (new \App\Utils\ModuleUtil())->hasThePermissionInSubscription($biz, $modulo, $callback)
        );
    });
}

it('UC-PIDX-25 · a aba Fiscal traz a origem gravada no produto, e diz que ela é por produto', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $importado = pdtProdutoComPrecos($this->business->id);
    $nacional = EstoqueFixture::singleProduct($this->business->id);
    DB::table('products')->where('id', $importado->productId)->update(['origem_mercadoria' => 1]);

    $f = pdtDetalhe($this, $importado->productId)['fiscal'];
    expect($f['origem'])->toBe('1');
    expect($f['origem_por_produto'])->toBeTrue();

    // Discriminante: o outro produto, sem escolha, segue com o DEFAULT 0 da coluna — a origem
    // vem da linha do produto, não de um valor fixo do payload.
    expect(pdtDetalhe($this, $nacional->productId)['fiscal']['origem'])->toBe('0');
});

it('UC-PIDX-25 · produto de outra empresa com origem própria não abre no painel', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $alheio = EstoqueFixture::singleProduct($outro);
    DB::table('products')->where('id', $alheio->productId)->update(['origem_mercadoria' => 1]);
    $meu = EstoqueFixture::singleProduct($this->business->id);

    // Anti-vácuo: o próprio produto abre — o null do alheio não é painel quebrado.
    expect(pdtDetalhe($this, $meu->productId))->not->toBeNull();
    expect(pdtDetalhe($this, $alheio->productId))->toBeNull();
});

it('UC-PIDX-25 · "Abrir no módulo Fiscal" só vem com o módulo no pacote E a permissão de acessar o módulo', function () {
    $p = pdtProdutoComPrecos($this->business->id);

    // Controle positivo: módulo no pacote + fiscal.access + nfe.tributacao.manage → a Tributação com o produto.
    pdtPacoteNfe(true);
    pdtEntrar($this, ['product.view', 'access_all_locations', 'fiscal.access', 'nfe.tributacao.manage']);
    expect(pdtDetalhe($this, $p->productId)['fiscal']['link_modulo'])
        ->toBe(route('nfe-brasil.tributacao.index', ['produto' => $p->productId]) . '#simulador');

    // Sem a permissão de acessar o módulo (decisão [F] 2026-10-16), mesmo podendo simular: não vem.
    pdtEntrar($this, ['product.view', 'access_all_locations', 'nfe.tributacao.manage']);
    expect(pdtDetalhe($this, $p->productId)['fiscal']['link_modulo'])->toBeNull();

    // Com acesso ao módulo, mas sem a permissão do simulador: a tela recusaria a simulação.
    pdtEntrar($this, ['product.view', 'access_all_locations', 'fiscal.access']);
    expect(pdtDetalhe($this, $p->productId)['fiscal']['link_modulo'])->toBeNull();
});

// Teste à parte: o controller fica em cache na rota durante o teste, e o mock do pacote tem de
// valer desde a 1ª requisição.
it('UC-PIDX-25 · com a permissão, mas o módulo Fiscal fora do pacote da empresa, o botão não vem', function () {
    $p = pdtProdutoComPrecos($this->business->id);
    pdtPacoteNfe(false);
    pdtEntrar($this, ['product.view', 'access_all_locations', 'fiscal.access', 'nfe.tributacao.manage']);
    expect(pdtDetalhe($this, $p->productId)['fiscal']['link_modulo'])->toBeNull();
});

// A aba Fiscal e o "Dados fiscais" do menu só existem em empresa que emite nota (decisão [F]
// 2026-10-26). O teste é o pacote, não a permissão: imposto e NCM não são segredo, só não servem a
// quem vende sem nota. Dois testes à parte pelo mesmo motivo do de cima (controller em cache).
it('UC-PIDX-25 · com o módulo Fiscal no pacote, a lista sabe que a empresa emite nota', function () {
    pdtPacoteNfe(true);
    // Sem `fiscal.access`: emitir nota é da empresa, não da permissão de quem consulta.
    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $r = $this->get('/products', $this->inertia);
    $r->assertOk();
    expect($r->json('props.contexto.emite_nota'))->toBeTrue();
});

it('UC-PIDX-25 · sem o módulo Fiscal no pacote, a lista sabe que a empresa não emite nota', function () {
    pdtPacoteNfe(false);
    pdtEntrar($this, ['product.view', 'access_all_locations', 'fiscal.access']);
    $r = $this->get('/products', $this->inertia);
    $r->assertOk();
    expect($r->json('props.contexto'))->toHaveKey('grupos_de_preco'); // anti-vácuo: o contexto veio
    expect($r->json('props.contexto.emite_nota'))->toBeFalse();
});
