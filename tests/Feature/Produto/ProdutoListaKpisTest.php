<?php

declare(strict_types=1);
// Cobre UC-PIDX-07 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Indicadores do topo da lista de produtos (`Produto/Index`, prop deferida `kpis`) — protótipo
 * `prototipo-ui/cowork/Felipe/produto-blade.jsx`, vista `lista`, os 4 `KpiCard`.
 *
 * Cada número é provado por um caminho que NÃO é o do indicador:
 *   · contagens × as linhas da própria lista sem filtro (prop `lista`, todas as linhas);
 *   · valor em estoque (custo) × a soma, linha a linha, da coluna `stock_price` do JSON do
 *     DataTable do relatório de estoque (o código que a lista Blade usa) — decisão [F]
 *     2026-10-10, opção "B" — e × a conta à mão do quanto ele sobe com uma compra conhecida.
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
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function plkEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Uma prop deferida da Page, pedida por partial reload. Cabeçalhos por requisição (não vazam). */
function plkProp($teste, string $prop, array $extra = [], string $query = ''): array
{
    $r = $teste->get('/products' . $query, $teste->inertia + ['X-Inertia-Partial-Data' => $prop] + $extra);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect($r->json('props'))->toHaveKey($prop);

    return $r->json('props.' . $prop);
}

/** Soma da coluna `stock_price` (valor pelo preço de compra) de TODAS as linhas do DataTable do relatório. */
function plkSomaRelatorio($teste): float
{
    $colunas = [];
    foreach ([['sku', 'variations.sub_sku'], ['stock', 'stock'], ['stock_price', 'stock_price']] as $i => [$d, $n]) {
        $colunas[$i] = ['data' => $d, 'name' => $n, 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']];
    }
    $dt = $teste->get('/reports/stock-report?' . http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
        'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false']]),
        ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    $soma = 0.0;
    foreach ($dt->json('data') as $linha) {
        $soma += (float) (preg_match('/data-orig-value="([^"]*)"/', (string) ($linha['stock_price'] ?? ''), $m) ? $m[1] : 0);
    }

    return $soma;
}

/** Compra recebida de um produto novo, num local novo: [quantidade, preço de compra]. */
function plkCompra(int $businessId, int $criador, float $quantidade, float $custo): void
{
    $local = EstoqueFixture::locationId($businessId, '-PLK-' . uniqid());
    $p = EstoqueFixture::singleProduct($businessId);
    EstoqueFixture::setStock($p, 0, $local, $quantidade);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'paid',
        'transaction_date' => '2099-01-01 10:00:00', 'final_total' => $quantidade * $custo, 'total_before_tax' => $quantidade * $custo,
        'created_by' => $criador, 'essentials_duration' => 0, 'ref_no' => 'PLK-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => $quantidade,
        'purchase_price' => $custo, 'purchase_price_inc_tax' => $custo, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PIDX-07 · as contagens dos indicadores batem com as linhas da própria lista, sem filtro', function () {
    $biz = $this->business->id;
    $variavel = EstoqueFixture::variableProduct($biz, 2);
    $inativo = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $inativo->productId)->update(['is_inactive' => 1]);
    $baixo = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $baixo->productId)->update(['alert_quantity' => 5]);
    EstoqueFixture::setStock($baixo, 0, EstoqueFixture::locationId($biz), 2);

    plkEntrar($this, ['product.view', 'access_all_locations']);
    $kpis = plkProp($this, 'kpis');
    $linhas = plkProp($this, 'lista', [], '?per_page=-1')['data'];
    $ids = array_column($linhas, 'id');

    // Anti-vácuo: os produtos do cenário estão na lista — senão as contagens provariam nada.
    expect($ids)->toContain($variavel->productId, $inativo->productId, $baixo->productId);

    expect($kpis['total'])->toBe(count($linhas));
    expect($kpis['variaveis'])->toBe(count(array_filter($linhas, fn ($l) => $l['tipo'] === 'variable')));
    expect($kpis['composicoes'])->toBe(count(array_filter($linhas, fn ($l) => $l['tipo'] === 'combo')));
    expect($kpis['inativos'])->toBe(count(array_filter($linhas, fn ($l) => $l['inativo'] === true)));
    expect($kpis['abaixo_do_alerta'])->toBe(count(array_filter($linhas, fn ($l) => $l['abaixo_do_alerta'] === true)));
    expect($kpis['inativos'])->toBeGreaterThanOrEqual(1);
    expect($kpis['abaixo_do_alerta'])->toBeGreaterThanOrEqual(1);
});

it('UC-PIDX-07 · o valor em estoque (custo) é o total do relatório de estoque e sobe exatamente o valor da compra', function () {
    plkEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'view_product_stock_value']);

    // 1ª leitura recalcula: o cache sobrevive entre execuções e pode trazer um valor velho.
    $antes = plkProp($this, 'kpis', ['X-Produto-Recalcular' => '1'])['valor_custo'];
    expect($antes)->not->toBeNull();
    expect(abs($antes - plkSomaRelatorio($this)))->toBeLessThan(0.005);

    plkCompra($this->business->id, $this->user->id, 7, 12.5);

    // Sem recalcular, o valor guardado (10 min) não muda; com o botão "Atualizado", muda.
    expect(plkProp($this, 'kpis')['valor_custo'])->toBe($antes);
    $depois = plkProp($this, 'kpis', ['X-Produto-Recalcular' => '1'])['valor_custo'];

    expect(abs(($depois - $antes) - 7 * 12.5))->toBeLessThan(0.005);
    expect(abs($depois - plkSomaRelatorio($this)))->toBeLessThan(0.005);
});

it('UC-PIDX-07 · sem a permissão de ver valor do estoque o indicador de valor não vem', function () {
    plkEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view']);
    $kpis = plkProp($this, 'kpis');

    expect($kpis)->toHaveKey('valor_custo');
    expect($kpis['valor_custo'])->toBeNull();
    expect($kpis['total'])->toBeGreaterThanOrEqual(0);
});

it('UC-PIDX-07 · os indicadores só contam o business atual', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Sem 2º business semeado.');
    }
    plkEntrar($this, ['product.view', 'access_all_locations']);
    $antes = plkProp($this, 'kpis')['total'];

    EstoqueFixture::singleProduct($outro);

    expect(plkProp($this, 'kpis')['total'])->toBe($antes);
});
