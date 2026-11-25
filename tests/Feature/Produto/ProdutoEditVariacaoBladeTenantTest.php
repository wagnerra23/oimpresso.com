<?php

declare(strict_types=1);

use App\Product;
use App\ProductVariation;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Editar produto variável pela tela antiga (Blade): `PUT /products/{id}` com `product_variation_edit`,
 * que vai para `ProductUtil::updateVariableProductVariations`.
 *
 * ÂNCORA: ADR 0093 (multi-tenant Tier 0) + REGRA MESTRE de valor (proibicoes.md). Até 2026-11-25 o
 * método fazia `ProductVariation::find($key)` sem conferir o produto: um id de grupo de variação de
 * outra empresa no envio renomeava o grupo dela e regravava o preço das variações dele.
 *
 * ⛔ Tenant 98 (ADR 0358) contra o vizinho fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
uses(DatabaseTransactions::class);

/** As chaves que o `update()` lê sempre (as mesmas do ProdutoEditPrecoContratoTest). */
function variacaoBladePayload(Product $produto, array $extra = []): array
{
    $campos = [];
    for ($i = 1; $i <= 20; $i++) {
        $campos['product_custom_field' . $i] = $produto->{'product_custom_field' . $i} ?? '';
    }

    return $extra + $campos + [
        'name' => $produto->name,
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'sub_category_id' => $produto->sub_category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'warranty_id' => $produto->warranty_id ?? '',
        'expiry_period' => '',
        'expiry_period_type' => '',
        'preparation_time_in_minutes' => '',
    ];
}

/** O `product_variation_edit` que o edit.blade.php manda para um grupo com uma variação. */
function variacaoBladeEnvio(int $grupoId, int $variacaoId, string $nomeGrupo, string $preco): array
{
    return [
        $grupoId => [
            'name' => $nomeGrupo,
            'variations_edit' => [
                $variacaoId => [
                    'value' => 'Var 1',
                    'default_purchase_price' => $preco,
                    'dpp_inc_tax' => $preco,
                    'profit_percent' => '0',
                    'default_sell_price' => $preco,
                    'sell_price_inc_tax' => $preco,
                ],
            ],
        ],
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    try {
        $this->business = $this->seededTenant();
        $this->vizinho = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.update']);
});

it('grupo de variação de produto de outra empresa não é renomeado nem tem o preço regravado [T0]', function () {
    $meu = EstoqueFixture::variableProduct($this->business->id, 1);
    $alheio = EstoqueFixture::variableProduct($this->vizinho->id, 1);
    $minha = $meu->variations[0];
    $alheia = $alheio->variations[0];
    Variation::whereKey($alheia['variation_id'])->update([
        'default_purchase_price' => 50, 'dpp_inc_tax' => 50, 'default_sell_price' => 60, 'sell_price_inc_tax' => 60,
    ]);
    $nomeAlheio = ProductVariation::findOrFail($alheia['product_variation_id'])->name;
    $produto = Product::findOrFail($meu->productId);

    // Controle positivo: o MESMO envio, com o grupo do próprio produto, chega ao save.
    $this->put("/products/{$meu->productId}", variacaoBladePayload($produto, [
        'name' => $produto->name . ' (controle)',
        'product_variation_edit' => variacaoBladeEnvio($minha['product_variation_id'], $minha['variation_id'], 'Meu grupo', '2'),
    ]));
    expect(Product::findOrFail($meu->productId)->name)->toBe($produto->name . ' (controle)');
    expect(ProductVariation::findOrFail($minha['product_variation_id'])->name)->toBe('Meu grupo');
    expect((float) Variation::findOrFail($minha['variation_id'])->default_sell_price)->toBe(2.0);

    $this->put("/products/{$meu->productId}", variacaoBladePayload($produto, [
        'name' => $produto->name . ' (invasão)',
        'product_variation_edit' => variacaoBladeEnvio($alheia['product_variation_id'], $alheia['variation_id'], 'INVASOR', '1'),
    ]));

    expect(ProductVariation::findOrFail($alheia['product_variation_id'])->name)->toBe($nomeAlheio);
    $v = Variation::findOrFail($alheia['variation_id']);
    expect((float) $v->default_purchase_price)->toBe(50.0);
    expect((float) $v->default_sell_price)->toBe(60.0);
    // O update inteiro voltou: o nome do próprio produto e a variação dele ficam como no controle.
    expect(Product::findOrFail($meu->productId)->name)->toBe($produto->name . ' (controle)');
    expect(Variation::where('product_id', $meu->productId)->count())->toBe(1);
});
