<?php

declare(strict_types=1);
// Cobre UC-PIDX-47 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Contracts\Fabricacao\ReceitaDoProduto;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\RecipeBomService;
use Tests\Support\EstoqueFixture;

/**
 * A receita da Fabricação no painel do produto (D-29, decisão [F] 2026-11-12): só leitura — insumos,
 * quantidades, custo pela receita e margem, com "Abrir receita" na Fabricação. Os números são os da tela
 * de Receitas: o painel não recalcula. Custo e margem só para quem vê o preço de compra.
 *
 * O número é provado por três caminhos: a conta à mão, a tela de Receitas (`listRecipesWithCost`) e o
 * painel. Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
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
        'X-Inertia-Partial-Data' => 'detalhe',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
    // A Fabricação com a conta real da tela de Receitas.
    app()->instance(ReceitaDoProduto::class, new RecipeBomService());
});

function prpEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function prpReceitas($teste, int $productId): ?array
{
    $r = $teste->get('/products?ver=' . $productId, $teste->inertia);
    $r->assertOk();
    expect($r->json('props.detalhe.id'))->toBe($productId);

    return $r->json('props.detalhe.receitas');
}

/**
 * Banner feito de 2 de lona; a lona custa 10 (`dpp_inc_tax`); custo de produção 10% sobre os insumos;
 * a receita rende 1 e o preço final dela é 44. Conta à mão: insumos 2 × 10 = 20; produção 10% de 20 = 2;
 * custo 22; por unidade 22 ÷ 1 = 22; margem (44 − 22) ÷ 44 = 50%.
 */
function prpCenario(int $biz): array
{
    $banner = EstoqueFixture::singleProduct($biz);
    $lona = EstoqueFixture::singleProduct($biz);
    DB::table('variations')->where('id', $lona->variations[0]['variation_id'])->update(['dpp_inc_tax' => 10, 'default_purchase_price' => 10]);
    $receita = DB::table('mfg_recipes')->insertGetId([
        'product_id' => $banner->productId, 'variation_id' => $banner->variations[0]['variation_id'], 'total_quantity' => 1,
        'final_price' => 44, 'extra_cost' => 10, 'production_cost_type' => 'percentage', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('mfg_recipe_ingredients')->insert([
        'mfg_recipe_id' => $receita, 'variation_id' => $lona->variations[0]['variation_id'], 'quantity' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$banner, $lona, $receita];
}

it('UC-PIDX-47 · o painel mostra insumos, custo e margem da receita, os mesmos da tela de Receitas', function () {
    [$banner, $lona, $receita] = prpCenario($this->business->id);
    prpEntrar($this, ['product.view', 'view_purchase_price', 'manufacturing.access_recipe', 'access_all_locations']);

    $receitas = prpReceitas($this, $banner->productId);
    expect($receitas)->toHaveCount(1);
    $r = $receitas[0];

    // Caminho 1: a conta à mão.
    expect($r['custo_total'])->toEqual(22.0);
    expect($r['custo_unitario'])->toEqual(22.0);
    expect($r['margem'])->toEqual(50.0);
    // Caminho 2: a tela de Receitas da Fabricação, para a mesma receita.
    $daTela = collect(app(RecipeBomService::class)->listRecipesWithCost($this->business->id))->firstWhere('id', $receita);
    expect($r['custo_total'])->toEqual($daTela['custos']['total']);
    expect($r['custo_unitario'])->toEqual($daTela['custos']['unit']);
    expect($r['margem'])->toEqual($daTela['custos']['margem']);

    $lonaSku = DB::table('variations')->where('id', $lona->variations[0]['variation_id'])->value('sub_sku');
    expect($r['insumos'])->toHaveCount(1);
    expect($r['insumos'][0]['sku'])->toBe($lonaSku);
    expect($r['insumos'][0]['quantidade'])->toEqual(2.0);
    expect($r['link'])->toBe('/manufacturing/recipe?q=' . rawurlencode($r['sku']));
});

it('UC-PIDX-47 · sem ver o preço de compra não vêm custo nem margem; sem abrir receitas, não vem o link', function () {
    [$banner] = prpCenario($this->business->id);
    prpEntrar($this, ['product.view', 'access_all_locations']);

    $r = prpReceitas($this, $banner->productId)[0];

    expect($r['insumos'])->toHaveCount(1); // os insumos não são segredo
    expect($r['custo_total'])->toBeNull();
    expect($r['custo_unitario'])->toBeNull();
    expect($r['margem'])->toBeNull();
    expect($r['link'])->toBeNull();
});

it('UC-PIDX-47 · produto sem receita não traz receita; a receita só sai para a empresa dona dela', function () {
    [$banner, $lona] = prpCenario($this->business->id);
    prpEntrar($this, ['product.view', 'view_purchase_price', 'access_all_locations']);

    expect(prpReceitas($this, $lona->productId))->toBe([]);
    // Tier 0: a mesma receita pedida por outra empresa não existe.
    $outra = (int) DB::table('business')->where('id', '!=', $this->business->id)->value('id');
    expect($outra)->toBeGreaterThan(0);
    expect(app(RecipeBomService::class)->fichasDoProduto($banner->productId, $outra))->toBe([]);
    expect(app(RecipeBomService::class)->fichasDoProduto($banner->productId, $this->business->id))->toHaveCount(1);
});
