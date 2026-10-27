<?php

declare(strict_types=1);
// Cobre UC-RES-01, UC-RES-02, UC-RES-03, UC-RES-04, UC-RES-05, UC-RES-06, UC-RES-07, UC-RES-08 (resources/js/Pages/Relatorios/Estoque/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Estoque e valores por três caminhos: JSON do DataTable da Blade × props da Page × conta à mão. Local próprio do teste
// (o relatório filtra por local). Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->tudo = ['stock_report.view', 'access_all_locations', 'view_product_stock_value', 'access_default_selling_price'];
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    resEntrar($this, $this->tudo);
});

/** Entra como um usuário do tenant 98 com as permissões dadas (fica em $teste->user). */
function resEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    // A Blade formata com num_f, que lê a moeda que o SetSessionData põe na sessão no login.
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Produto com estoque no local: [estoque, preço de venda, comprado, preço de compra, vendido]. */
function resProduto(int $businessId, int $local, int $criador, string $sku, array $v): void
{
    [$estoque, $venda, $comprado, $custo, $vendido] = $v;
    $p = EstoqueFixture::singleProduct($businessId);
    $variacao = $p->variations[0]['variation_id'];
    DB::table('variations')->where('id', $variacao)->update(['sub_sku' => $sku, 'sell_price_inc_tax' => $venda]);
    EstoqueFixture::setStock($p, 0, $local, $estoque);
    // Com filtro de local, o relatório só lista produto disponível no local (product_locations).
    DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'paid',
        'transaction_date' => '2099-01-01 10:00:00', 'final_total' => $comprado * $custo, 'total_before_tax' => $comprado * $custo,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'RES-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $variacao, 'quantity' => $comprado,
        'purchase_price' => $custo, 'purchase_price_inc_tax' => $custo, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($vendido > 0) {
        $venda_id = DB::table('transactions')->insertGetId([
            'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
            'transaction_date' => '2099-01-02 10:00:00', 'final_total' => $vendido * $venda, 'total_before_tax' => $vendido * $venda,
            'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RES-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $venda_id, 'product_id' => $p->productId, 'variation_id' => $variacao, 'quantity' => $vendido,
            'quantity_returned' => 0, 'unit_price' => $venda, 'unit_price_inc_tax' => $venda, 'unit_price_before_discount' => $venda,
            'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

function resTela($teste, int $local, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/stock-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local, 'page' => $pagina]));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Estoque/Index');

    return $r->json('props');
}

test('UC-RES-01 estoque e valor — iguais ao JSON do DataTable da Blade e à conta à mão', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
    resProduto($this->business->id, $local, $this->user->id, 'RES-A-'.uniqid(), [7, 20, 7, 12, 2]);

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do report.js.
    $colunas = [];
    foreach ([['action', 'action', 'false'], ['sku', 'variations.sub_sku', 'true'], ['product', 'p.name', 'true'], ['variation', 'variation', 'true'], ['category_name', 'c.name', 'true'], ['location_name', 'l.name', 'true'], ['unit_price', 'variations.sell_price_inc_tax', 'true'], ['stock', 'stock', 'false'], ['stock_price', 'stock_price', 'false'], ['stock_value_by_sale_price', 'stock_value_by_sale_price', 'false'], ['potential_profit', 'potential_profit', 'false'], ['total_sold', 'total_sold', 'false'], ['total_transfered', 'total_transfered', 'false'], ['total_adjusted', 'total_adjusted', 'false']] as $i => [$d, $n, $busca]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $busca, 'orderable' => in_array($d, ['action', 'stock_value_by_sale_price', 'potential_profit'], true) ? 'false' : 'true', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/stock-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 1, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'location_id' => $local]));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $orig = fn (string $c) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $dt->json('data.0.'.$c), $m) ? $m[1] : 'NaN');

    // Caminho 2: a Page.
    $linha = resTela($this, $local)['linhas'][0];
    $tela = [(float) $linha['estoque'], (float) $linha['valor_compra'], (float) $linha['valor_venda'], (float) $linha['lucro_potencial'], (float) $linha['vendido']];
    expect($tela)->toEqual([$orig('stock'), $orig('stock_price'), $orig('stock_value_by_sale_price'), $orig('potential_profit'), $orig('total_sold')]);

    // Conta à mão: estoque 7; valor pela compra 7 × 12 = 84; pela venda 7 × 20 = 140; lucro 140 − 84 = 56; vendido 2.
    expect($tela)->toEqual([7.0, 84.0, 140.0, 56.0, 2.0]);
});

test('UC-RES-02 — preço e valores só com a permissão, como na Blade', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
    resProduto($this->business->id, $local, $this->user->id, 'RES-B-'.uniqid(), [7, 20, 7, 12, 0]);

    resEntrar($this, ['stock_report.view', 'access_all_locations']);
    $props = resTela($this, $local);
    expect([$props['mostra_valor'], $props['mostra_preco']])->toBe([false, false]);
    expect([$props['linhas'][0]['valor_compra'], $props['linhas'][0]['valor_venda'], $props['linhas'][0]['lucro_potencial'], $props['linhas'][0]['preco']])->toBe([null, null, null, null]);
    expect((float) $props['linhas'][0]['estoque'])->toEqual(7.0);
});

test('UC-RES-03 — 25 por página pelo SKU', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
    $sufixo = uniqid();
    for ($i = 0; $i < 26; $i++) {
        resProduto($this->business->id, $local, $this->user->id, sprintf('RES%02d-%s', $i, $sufixo), [1, 10, 1, 5, 0]);
    }

    $p1 = resTela($this, $local, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['sku'])->toBe('RES00-'.$sufixo);
    expect(array_column(resTela($this, $local, 2)['linhas'], 'sku'))->toBe(['RES25-'.$sufixo]);
});

test('UC-RES-04 Tier 0 — estoque do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RES-'.uniqid());
    resProduto($alheio->id, $localAlheio, $donoAlheio, 'RES-X-'.uniqid(), [9, 20, 9, 12, 0]);

    expect(resTela($this, $localAlheio)['linhas'])->toBe([]);

    // Sem a permissão: 403 na tela nova e na Blade.
    resEntrar($this, []);
    $this->withHeaders($this->inertia)->get('/reports/stock-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/stock-report')->assertForbidden();
});

test('UC-RES-05 Tier 0 — o JSON do DataTable só traz as colunas de valor com view_product_stock_value', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
    resProduto($this->business->id, $local, $this->user->id, 'RES-J-'.uniqid(), [7, 20, 7, 12, 0]);

    $json = function () use ($local) {
        $colunas = [];
        foreach ([['sku', 'variations.sub_sku'], ['stock', 'stock'], ['stock_price', 'stock_price'], ['stock_value_by_sale_price', 'stock_value_by_sale_price'], ['potential_profit', 'potential_profit']] as $i => [$d, $n]) {
            $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => $d === 'sku' ? 'true' : 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
        }
        $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/reports/stock-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
                'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'location_id' => $local]));
        $r->assertOk();
        expect($r->json('error'))->toBeNull();
        $l = $r->json('data.0');
        $orig = fn ($v) => $v === null ? null : (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $v, $m) ? $m[1] : 'NaN');

        return [$orig($l['stock_price']), $orig($l['stock_value_by_sale_price']), $orig($l['potential_profit'])];
    };

    // Com a permissão: os mesmos números de antes (84 · 140 · 56).
    expect($json())->toBe([84.0, 140.0, 56.0]);

    // Sem a permissão: as três colunas vêm vazias, como a Blade já fazia na tela.
    resEntrar($this, ['stock_report.view', 'access_all_locations']);
    expect($json())->toBe([null, null, null]);
});

/** A prop adiada `resumo`, pedida como a Page pede (partial reload Inertia). */
function resResumo($teste, array $query): ?array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->flushHeaders()->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Relatorios/Estoque/Index', 'X-Inertia-Partial-Data' => 'resumo',
    ])->get('/reports/stock-report?'.http_build_query(['tela' => 'nova'] + $query));
    $r->assertOk();

    return $r->json('props.resumo');
}

/** Coloca o produto do SKU na categoria/subcategoria dadas. */
function resCategorizar(string $sku, ?int $categoria, ?int $subcategoria): void
{
    $produto = DB::table('variations')->where('sub_sku', $sku)->value('product_id');
    DB::table('products')->where('id', $produto)->update(['category_id' => $categoria, 'sub_category_id' => $subcategoria]);
}

function resCategoria(int $businessId, string $nome, int $pai = 0): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $businessId, 'name' => $nome.' '.uniqid(), 'short_code' => 'RS'.random_int(100, 999), 'parent_id' => $pai,
        'category_type' => 'product', 'created_by' => EstoqueFixture::userId($businessId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-RES-06 valor — o resumo do topo é o do JSON da Blade (get-stock-value) e bate com a conta à mão', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES6-'.uniqid());
    resProduto($this->business->id, $local, $this->user->id, 'RES6-'.uniqid(), [7, 20, 7, 12, 0]);
    // O resumo é o estoque final de HOJE: a compra do fixture (2099) vira ontem, como no EstoqueResumoPermissaoTest.
    DB::table('transactions')->where('business_id', $this->business->id)->where('location_id', $local)->where('type', 'purchase')
        ->update(['transaction_date' => now()->subDay()->toDateTimeString()]);

    // Caminho 1: o JSON que o topo da Blade pede (get_stock_value em public/js/report.js).
    $blade = $this->flushHeaders()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/get-stock-value?'.http_build_query(['location_id' => $local]));
    $blade->assertOk();

    // Caminho 2: a prop adiada da Page.
    $resumo = resResumo($this, ['location_id' => $local]);
    $tela = [(float) $resumo['por_compra'], (float) $resumo['por_venda'], (float) $resumo['lucro_potencial'], (float) $resumo['margem']];
    expect($tela)->toEqual([(float) $blade->json('closing_stock_by_pp'), (float) $blade->json('closing_stock_by_sp'),
        (float) $blade->json('potential_profit'), (float) $blade->json('profit_margin')]);

    // Conta à mão: 7 × 12 = 84 pela compra; 7 × 20 = 140 pela venda; lucro 56; margem 56 ÷ 140 = 40%.
    expect($tela)->toEqual([84.0, 140.0, 56.0, 40.0]);

    // Sem view_product_stock_value: o resumo não vem (a Blade esconde o bloco com @can).
    resEntrar($this, ['stock_report.view', 'access_all_locations']);
    $r = $this->flushHeaders()->withHeaders($this->inertia)->get('/reports/stock-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local]));
    $r->assertOk();
    expect($r->json('props.resumo'))->toBeNull();
    expect($r->json('props.mostra_valor'))->toBeFalse();
});

test('UC-RES-07 — filtro de subcategoria igual ao do DataTable da Blade, com as subcategorias da categoria', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES7-'.uniqid());
    $categoria = resCategoria($this->business->id, 'RES7 cat');
    $sub1 = resCategoria($this->business->id, 'RES7 sub1', $categoria);
    $sub2 = resCategoria($this->business->id, 'RES7 sub2', $categoria);
    $skuA = 'RES7-A-'.uniqid();
    $skuB = 'RES7-B-'.uniqid();
    resProduto($this->business->id, $local, $this->user->id, $skuA, [5, 10, 5, 4, 0]);
    resProduto($this->business->id, $local, $this->user->id, $skuB, [3, 10, 3, 4, 0]);
    resCategorizar($skuA, $categoria, $sub1);
    resCategorizar($skuB, $categoria, $sub2);

    // Tier 0: uma "subcategoria" do negócio 99 pendurada na mesma categoria não aparece no select.
    $alheio = $this->seededSupportClientTenant();
    $subAlheia = resCategoria($alheio->id, 'RES7 alheia', $categoria);

    $filtro = ['location_id' => $local, 'category_id' => $categoria, 'sub_category_id' => $sub1];
    $props = $this->flushHeaders()->withHeaders($this->inertia)
        ->get('/reports/stock-report?'.http_build_query(['tela' => 'nova'] + $filtro))->json('props');
    expect(collect($props['linhas'])->pluck('sku')->all())->toBe([$skuA]);
    expect(collect($props['subcategorias'])->pluck('id')->sort()->values()->all())->toBe(collect([$sub1, $sub2])->sort()->values()->all());
    expect(collect($props['subcategorias'])->pluck('id'))->not->toContain($subAlheia);
    expect($props['filtros']['sub_category_id'])->toBe((string) $sub1);

    // O mesmo filtro no JSON do DataTable da Blade traz o mesmo produto.
    $dt = $this->flushHeaders()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/stock-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1] + $filtro));
    $dt->assertOk();
    expect(collect($dt->json('data'))->pluck('sku')->all())->toBe([$skuA]);

    // Subcategoria sem categoria é ignorada, como na Blade (o select dela depende da categoria).
    $semCategoria = $this->flushHeaders()->withHeaders($this->inertia)
        ->get('/reports/stock-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local, 'sub_category_id' => $sub1]))->json('props');
    expect(collect($semCategoria['linhas'])->pluck('sku')->sort()->values()->all())->toBe(collect([$skuA, $skuB])->sort()->values()->all());
    expect($semCategoria['subcategorias'])->toBe([]);
});

test('UC-RES-08 — "só fabricação" segue o Manufacturing: sem o módulo é ignorado; com ele, igual ao DataTable da Blade', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RES8-'.uniqid());
    $sku = 'RES8-'.uniqid();
    resProduto($this->business->id, $local, $this->user->id, $sku, [4, 10, 4, 5, 0]);

    $tela = fn (array $q) => $this->flushHeaders()->withHeaders($this->inertia)
        ->get('/reports/stock-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local] + $q))->json('props');
    $comFiltro = $tela(['only_mfg_products' => 1]);

    if (! $comFiltro['mostra_fabricacao']) {
        // Sem o Manufacturing a Blade nem mostra o checkbox, e o filtro não chega à consulta (ele junta mfg_recipes).
        expect($comFiltro['filtros']['only_mfg_products'])->toBe('');
        expect(collect($comFiltro['linhas'])->pluck('sku')->all())->toBe(collect($tela([])['linhas'])->pluck('sku')->all());

        return;
    }

    // Com o módulo: o produto sem receita sai, como no JSON do DataTable da Blade com o mesmo filtro.
    expect($comFiltro['filtros']['only_mfg_products'])->toBe('1');
    $dt = $this->flushHeaders()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/stock-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'location_id' => $local, 'only_mfg_products' => 1]));
    $dt->assertOk();
    // A Page ordena pelo SKU; sem `order` o DataTable não garante ordem — compara os conjuntos.
    expect(collect($comFiltro['linhas'])->pluck('sku')->sort()->values()->all())->toBe(collect($dt->json('data'))->pluck('sku')->sort()->values()->all());
    expect(collect($comFiltro['linhas'])->pluck('sku'))->not->toContain($sku);
});
