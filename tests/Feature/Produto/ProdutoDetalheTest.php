<?php

declare(strict_types=1);
// Cobre UC-PIDX-09 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

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
