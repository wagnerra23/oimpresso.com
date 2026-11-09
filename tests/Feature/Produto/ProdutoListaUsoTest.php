<?php

declare(strict_types=1);
// Cobre UC-PIDX-44 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Contracts\Fabricacao\ProdutosDeReceita;
use App\Contracts\Fiscal\NaturezaDoProduto;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProdutosDeReceitaService;
use Tests\Support\EstoqueFixture;

/**
 * Filtro "Uso" da consulta de produto (CV1, `?uso=`): Material é o produto que entra como insumo em alguma
 * receita da Fabricação (a SQL real de `ProdutosDeReceitaService`); Serviço é a natureza fiscal "servico";
 * Para vender é nem um nem outro. O cenário é o do caso: lona (entra na receita do banner), banner e
 * instalação (serviço).
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration') || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Schema UltimatePOS/Fabricação ausente — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
    // A Fabricação com a SQL real; a natureza por um dublê (a tabela dela é do módulo Fiscal).
    app()->instance(ProdutosDeReceita::class, new ProdutosDeReceitaService());
});

/** Natureza fiscal fixa: os ids passados são serviço; o resto, mercadoria. */
function pluServicos(array $ids): void
{
    app()->instance(NaturezaDoProduto::class, new class($ids) implements NaturezaDoProduto
    {
        public function __construct(private array $ids) {}

        public function dosProdutos(int $businessId, array $productIds): array
        {
            return [];
        }

        public function produtosCom(int $businessId, string $natureza): array
        {
            return $natureza === 'servico' ? $this->ids : [];
        }
    });
}

function pluEntrar($teste): void
{
    $teste->user = $teste->usuarioComPermissoes(['product.view', 'access_all_locations'], $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pluIds($teste, string $query): array
{
    $r = $teste->get('/products' . $query, $teste->inertia + ['X-Inertia-Partial-Data' => 'lista']);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    return array_column($r->json('props.lista.data'), 'id');
}

/** Receita do produto `$feito` que usa a variação do produto `$insumo`. */
function pluReceita(object $feito, object $insumo): void
{
    $r = DB::table('mfg_recipes')->insertGetId([
        'product_id' => $feito->productId, 'variation_id' => $feito->variations[0]['variation_id'], 'total_quantity' => 1,
        'final_price' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('mfg_recipe_ingredients')->insert([
        'mfg_recipe_id' => $r, 'variation_id' => $insumo->variations[0]['variation_id'], 'quantity' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-PIDX-44 · Material mostra o que entra em receita; Serviço, a natureza; Para vender, o resto', function () {
    $biz = $this->business->id;
    $lona = EstoqueFixture::singleProduct($biz);
    $banner = EstoqueFixture::singleProduct($biz);
    $instalacao = EstoqueFixture::singleProduct($biz);
    pluReceita($banner, $lona);
    pluServicos([$instalacao->productId]);

    pluEntrar($this);
    $material = pluIds($this, '?uso=material&per_page=-1');
    $servico = pluIds($this, '?uso=servico&per_page=-1');
    $vender = pluIds($this, '?uso=vender&per_page=-1');

    expect($material)->toContain($lona->productId);
    expect($material)->not->toContain($banner->productId);
    expect($material)->not->toContain($instalacao->productId);
    expect($servico)->toBe([$instalacao->productId]);
    expect($vender)->toContain($banner->productId);
    expect($vender)->not->toContain($lona->productId);
    expect($vender)->not->toContain($instalacao->productId);
});

it('UC-PIDX-44 · as três opções somadas dão a lista inteira, sem repetir', function () {
    $biz = $this->business->id;
    $lona = EstoqueFixture::singleProduct($biz);
    $banner = EstoqueFixture::singleProduct($biz);
    $instalacao = EstoqueFixture::singleProduct($biz);
    pluReceita($banner, $lona);
    pluServicos([$instalacao->productId]);

    pluEntrar($this);
    $toda = pluIds($this, '?per_page=-1');
    $somadas = array_merge(pluIds($this, '?uso=vender&per_page=-1'), pluIds($this, '?uso=material&per_page=-1'), pluIds($this, '?uso=servico&per_page=-1'));
    sort($toda);
    sort($somadas);

    expect($somadas)->toBe($toda);
});

it('UC-PIDX-44 · receita de outra empresa não faz material aqui; o contexto oferece só as opções que existem', function () {
    $biz = $this->business->id;
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $lonaDeFora = EstoqueFixture::singleProduct((int) $outra);
    $bannerDeFora = EstoqueFixture::singleProduct((int) $outra);
    pluReceita($bannerDeFora, $lonaDeFora);
    $meu = EstoqueFixture::singleProduct($biz);
    pluServicos([]);

    expect(app(ProdutosDeReceita::class)->materiais($biz))->not->toContain($lonaDeFora->productId);
    expect(app(ProdutosDeReceita::class)->materiais($biz))->not->toContain($meu->productId);
    expect(app(ProdutosDeReceita::class)->materiais((int) $outra))->toContain($lonaDeFora->productId);

    pluEntrar($this);
    $r = $this->get('/products', $this->inertia + ['X-Inertia-Partial-Data' => 'contexto']);
    $r->assertOk();
    expect($r->json('props.contexto.usos'))->toBe(['vender', 'material', 'servico']);
    expect(pluIds($this, '?uso=material&per_page=-1'))->not->toContain($lonaDeFora->productId);
});
