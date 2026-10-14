<?php

declare(strict_types=1);
// Cobre UC-PIDX-08 e UC-PIDX-18 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Aba "Relatório de estoque" da lista de produtos (`Produto/Index`, prop `estoque`) — protótipo
 * `prototipo-ui/cowork/Felipe/produto-blade.jsx`, vista `lista`, `RelatorioEstoque`; a aba
 * `#product_stock_report` da lista Blade.
 *
 * Provado por caminhos que NÃO são o da aba:
 *   · as linhas × as da tela de Relatórios → Estoque (`/reports/stock-report?tela=nova`), mesmo filtro;
 *   · os totais × a soma, linha a linha, do JSON do DataTable do relatório (o código da Blade);
 *   · sem filtro, o "valor pelo custo" do rodapé × o indicador "Valor em estoque (custo)" do topo.
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
    $this->tudo = ['product.view', 'access_all_locations', 'stock_report.view', 'view_product_stock_value', 'access_default_selling_price'];
});

function pleEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Uma prop da Page `Produto/Index` por partial reload; o 1º pedido recalcula (o cache sobrevive entre execuções). */
function pleProp($teste, string $prop, array $query = []): mixed
{
    $r = $teste->get('/products' . ($query ? '?' . http_build_query($query) : ''), $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => $prop,
        'X-Produto-Recalcular' => '1',
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect($r->json('props'))->toHaveKey($prop);

    return $r->json('props.' . $prop);
}

/** Produto com estoque num local próprio, comprado e vendido: [estoque, preço de venda, comprado, custo, vendido]. */
function pleProduto(int $businessId, int $local, int $criador, string $sku, array $v): void
{
    [$estoque, $venda, $comprado, $custo, $vendido] = $v;
    $p = EstoqueFixture::singleProduct($businessId);
    $variacao = $p->variations[0]['variation_id'];
    DB::table('variations')->where('id', $variacao)->update(['sub_sku' => $sku, 'sell_price_inc_tax' => $venda]);
    EstoqueFixture::setStock($p, 0, $local, $estoque);
    DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'paid',
        'transaction_date' => '2099-01-01 10:00:00', 'final_total' => $comprado * $custo, 'total_before_tax' => $comprado * $custo,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'PLE-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $variacao, 'quantity' => $comprado,
        'purchase_price' => $custo, 'purchase_price_inc_tax' => $custo, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($vendido > 0) {
        $vendaId = DB::table('transactions')->insertGetId([
            'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
            'transaction_date' => '2099-01-02 10:00:00', 'final_total' => $vendido * $venda, 'total_before_tax' => $vendido * $venda,
            'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'PLE-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $vendaId, 'product_id' => $p->productId, 'variation_id' => $variacao, 'quantity' => $vendido,
            'quantity_returned' => 0, 'unit_price' => $venda, 'unit_price_inc_tax' => $venda, 'unit_price_before_discount' => $venda,
            'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** Soma, linha a linha, de colunas do JSON do DataTable do relatório (todas as linhas). */
function pleSomaDataTable($teste, array $filtros = []): array
{
    $colunas = [];
    foreach ([['sku', 'variations.sub_sku'], ['stock', 'stock'], ['stock_price', 'stock_price'], ['stock_value_by_sale_price', 'stock_value_by_sale_price'], ['total_sold', 'total_sold']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $teste->get('/reports/stock-report?' . http_build_query(array_merge(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
        'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false']], $filtros)),
        ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $orig = fn ($v) => (float) (preg_match('/data-orig-value="([^"]*)"/', (string) $v, $m) ? $m[1] : 0);
    $soma = ['linhas' => 0, 'estoque' => 0.0, 'valor_compra' => 0.0, 'valor_venda' => 0.0, 'vendido' => 0.0];
    foreach ($dt->json('data') as $l) {
        $soma['linhas']++;
        $soma['estoque'] += $orig($l['stock'] ?? '');
        $soma['valor_compra'] += $orig($l['stock_price'] ?? '');
        $soma['valor_venda'] += $orig($l['stock_value_by_sale_price'] ?? '');
        $soma['vendido'] += $orig($l['total_sold'] ?? '');
    }

    return $soma;
}

it('UC-PIDX-08 · as linhas da aba são as da tela de Relatórios → Estoque, com o mesmo filtro', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-PLE-' . uniqid());
    pleEntrar($this, $this->tudo);
    pleProduto($this->business->id, $local, $this->user->id, 'PLE-A-' . uniqid(), [7, 20, 7, 12, 2]);
    pleProduto($this->business->id, $local, $this->user->id, 'PLE-B-' . uniqid(), [3, 50, 3, 30, 0]);

    $aba = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local]);
    $r = $this->get('/reports/stock-report?' . http_build_query(['tela' => 'nova', 'location_id' => $local]), $this->inertia);
    $r->assertOk();
    $tela = $r->json('props.linhas');

    // Anti-vácuo: as duas linhas do cenário estão lá — comparar listas vazias provaria nada.
    expect($aba['linhas'])->toHaveCount(2);
    expect($aba['linhas'])->toEqual($tela);
    expect($aba['mostra_valor'])->toBeTrue();
});

it('UC-PIDX-08 · o rodapé soma TODAS as linhas e bate com o JSON do DataTable do relatório', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-PLE-' . uniqid());
    pleEntrar($this, $this->tudo);
    pleProduto($this->business->id, $local, $this->user->id, 'PLE-C-' . uniqid(), [7, 20, 7, 12, 2]);
    pleProduto($this->business->id, $local, $this->user->id, 'PLE-D-' . uniqid(), [3, 50, 3, 30, 0]);

    $totais = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local])['totais'];
    $dt = pleSomaDataTable($this, ['location_id' => $local]);

    expect($totais['linhas'])->toBe(2);
    expect($dt['linhas'])->toBe(2);
    // Conta à mão do cenário: estoque 7 + 3; custo 7×12 + 3×30; venda 7×20 + 3×50; vendido 2.
    expect(abs($totais['estoque'] - 10))->toBeLessThan(0.005);
    expect(abs($totais['valor_compra'] - (7 * 12 + 3 * 30)))->toBeLessThan(0.005);
    expect(abs($totais['valor_venda'] - (7 * 20 + 3 * 50)))->toBeLessThan(0.005);
    expect(abs($totais['vendido'] - 2))->toBeLessThan(0.005);
    // E o mesmo pelo código da Blade.
    foreach (['estoque', 'valor_compra', 'valor_venda', 'vendido'] as $c) {
        expect(abs($totais[$c] - $dt[$c]))->toBeLessThan(0.005);
    }
});

it('UC-PIDX-08 · sem filtro, o valor pelo custo do rodapé é o indicador "Valor em estoque (custo)"', function () {
    pleEntrar($this, $this->tudo);
    $local = EstoqueFixture::locationId($this->business->id, '-PLE-' . uniqid());
    pleProduto($this->business->id, $local, $this->user->id, 'PLE-E-' . uniqid(), [5, 20, 5, 9, 0]);

    $totais = pleProp($this, 'estoque', ['aba' => 'estoque'])['totais'];
    $kpis = pleProp($this, 'kpis');

    expect($totais['valor_compra'])->not->toBeNull();
    expect(abs($totais['valor_compra'] - $kpis['valor_custo']))->toBeLessThan(0.005);
});

it('UC-PIDX-08 · sem stock_report.view a aba não vem', function () {
    pleEntrar($this, ['product.view', 'access_all_locations']);

    expect(pleProp($this, 'estoque', ['aba' => 'estoque']))->toBeNull();
});

// ── UC-PIDX-18 · linhas por página da aba ───────────────────────────────────────────────────────
// 26 linhas num local próprio: com 25 por página sobra 1 para a página 2; com 50 cabem todas.

/** 26 produtos com estoque num local novo. Devolve [local, estoque total, valor pelo custo, valor pela venda]. */
function pleVinteESeis($teste): array
{
    $local = EstoqueFixture::locationId($teste->business->id, '-PLE-' . uniqid());
    $prefixo = 'PLE-P' . uniqid() . '-';
    $estoque = 0;
    $custo = 0;
    $venda = 0;
    for ($i = 1; $i <= 26; $i++) {
        // estoque i, preço de venda 20, custo 12, nada vendido
        pleProduto($teste->business->id, $local, $teste->user->id, $prefixo . str_pad((string) $i, 2, '0', STR_PAD_LEFT), [$i, 20, $i, 12, 0]);
        $estoque += $i;
        $custo += $i * 12;
        $venda += $i * 20;
    }

    return [$local, $estoque, $custo, $venda];
}

it('UC-PIDX-18 · a aba traz o tamanho de página pedido: 25 deixa uma linha para a página 2, 50 traz as 26', function () {
    pleEntrar($this, $this->tudo);
    [$local] = pleVinteESeis($this);

    $com25 = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => 25]);
    expect($com25['linhas'])->toHaveCount(25);
    expect($com25['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26, 'por_pagina' => 25]);

    $pagina2 = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => 25, 'estoque_pagina' => 2]);
    expect($pagina2['linhas'])->toHaveCount(1);
    expect($pagina2['paginacao'])->toMatchArray(['atual' => 2, 'ultima' => 2, 'total' => 26]);

    $com50 = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => 50]);
    expect($com50['linhas'])->toHaveCount(26);
    expect($com50['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 1, 'total' => 26, 'por_pagina' => 50]);

    // As duas páginas de 25 juntas são exatamente as 26 linhas da página de 50: nada repetido, nada faltando.
    $skus = fn (array $linhas) => array_map(fn ($l) => $l['sku'], $linhas);
    expect(array_merge($skus($com25['linhas']), $skus($pagina2['linhas'])))->toEqual($skus($com50['linhas']));
});

it('UC-PIDX-18 · "Todos" (-1) traz todas as linhas numa página só', function () {
    pleEntrar($this, $this->tudo);
    [$local] = pleVinteESeis($this);

    $todas = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => -1]);

    expect($todas['linhas'])->toHaveCount(26);
    expect($todas['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 1, 'total' => 26, 'por_pagina' => -1]);
});

it('UC-PIDX-18 · os totais do rodapé são os mesmos em qualquer tamanho de página, e batem com a conta à mão', function () {
    pleEntrar($this, $this->tudo);
    [$local, $estoque, $custo, $venda] = pleVinteESeis($this);

    $totais = [];
    foreach ([25, 50, -1] as $n) {
        $totais[$n] = pleProp($this, 'estoque', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => $n])['totais'];
    }

    // Caminho 1: conta à mão do cenário — estoque 1+2+…+26, custo ×12, venda ×20.
    expect($estoque)->toBe(351);
    foreach ([25, 50, -1] as $n) {
        expect($totais[$n]['linhas'])->toBe(26);
        expect(abs($totais[$n]['estoque'] - $estoque))->toBeLessThan(0.005);
        expect(abs($totais[$n]['valor_compra'] - $custo))->toBeLessThan(0.005);
        expect(abs($totais[$n]['valor_venda'] - $venda))->toBeLessThan(0.005);
        expect(abs($totais[$n]['lucro_potencial'] - ($venda - $custo)))->toBeLessThan(0.005);
    }
    // Caminho 2: o código da Blade (JSON do DataTable do relatório, todas as linhas).
    $dt = pleSomaDataTable($this, ['location_id' => $local]);
    expect($dt['linhas'])->toBe(26);
    foreach (['estoque', 'valor_compra', 'valor_venda'] as $c) {
        expect(abs($totais[25][$c] - $dt[$c]))->toBeLessThan(0.005);
    }
});

it('UC-PIDX-18 · tamanho fora das opções cai no padrão, que é o mesmo da lista', function () {
    pleEntrar($this, $this->tudo);
    [$local] = pleVinteESeis($this);

    $padrao = pleProp($this, 'filtros', ['aba' => 'estoque', 'location_id' => $local]);
    // Sem pedir nada, as duas abas nascem com o mesmo tamanho (na Blade era um menu só para os dois DataTables).
    expect($padrao['estoque_por_pagina'])->toBe($padrao['per_page']);
    expect([25, 50, 100, 200, 500, 1000, -1])->toContain($padrao['estoque_por_pagina']);

    foreach ([7, 0, 26, 99999] as $invalido) {
        $f = pleProp($this, 'filtros', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => $invalido]);
        expect($f['estoque_por_pagina'])->toBe($padrao['estoque_por_pagina']);
    }

    // E o tamanho da aba não mexe no da lista (nem o contrário).
    $f = pleProp($this, 'filtros', ['aba' => 'estoque', 'location_id' => $local, 'estoque_por_pagina' => 50, 'per_page' => 100]);
    expect($f['estoque_por_pagina'])->toBe(50);
    expect($f['per_page'])->toBe(100);
});
