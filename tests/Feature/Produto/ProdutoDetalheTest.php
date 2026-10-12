<?php

declare(strict_types=1);
// Cobre UC-PIDX-09, UC-PIDX-11 e UC-PIDX-12 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

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

it('UC-PIDX-11 · "Comprar" segue a permissão de criar compra (purchase.create)', function () {
    pdtEntrar($this, ['product.view', 'access_all_locations', 'purchase.create']);
    $com = $this->get('/products', $this->inertia);
    $com->assertOk();
    expect($com->json('props.permissions.comprar'))->toBeTrue();

    pdtEntrar($this, ['product.view', 'access_all_locations']);
    $sem = $this->get('/products', $this->inertia);
    $sem->assertOk();
    // Anti-vácuo: as permissões vieram — o false é do gate, não de prop faltando.
    expect($sem->json('props.permissions'))->toHaveKey('view');
    expect($sem->json('props.permissions.comprar'))->toBeFalse();
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
    foreach ([['sku', 'variations.sub_sku'], ['stock', 'stock'], ['stock_price', 'stock_price'], ['stock_value_by_sale_price', 'stock_value_by_sale_price'], ['potential_profit', 'potential_profit']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $teste->get('/reports/stock-report?' . http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
        'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'product_id' => $productId]),
        ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $orig = fn ($v) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $v, $m) ? $m[1] : 0);
    $soma = ['linhas' => 0, 'estoque' => 0.0, 'valor_compra' => 0.0, 'valor_venda' => 0.0, 'lucro_potencial' => 0.0];
    foreach ($dt->json('data') as $l) {
        $soma['linhas']++;
        $soma['estoque'] += $orig($l['stock'] ?? '');
        $soma['valor_compra'] += $orig($l['stock_price'] ?? '');
        $soma['valor_venda'] += $orig($l['stock_value_by_sale_price'] ?? '');
        $soma['lucro_potencial'] += $orig($l['potential_profit'] ?? '');
    }

    return $soma;
}

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
