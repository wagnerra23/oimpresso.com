<?php

declare(strict_types=1);
// Cobre UC-PEDIT-16 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste (ADR 0264).

use App\Product;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Preço do produto simples na tela Editar React (`GET /products/{id}/edit` Inertia + `PUT /products/{id}`).
 *
 * ÂNCORA: `CU-PROD-01` item 4 `[V0]` + REGRA MESTRE de valor (proibicoes.md) + ADR 0093.
 * O envio é o que `Edit.tsx` manda quando a pessoa mexe no preço (tests/js/produto-editar-preco.test.tsx):
 * `single_variation_id` + os cinco campos com vírgula decimal e sem milhar (precos.ts `camposDePreco`).
 *
 * ⛔ Tenant 98 (ADR 0358) contra o vizinho fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
uses(DatabaseTransactions::class);

/**
 * As chaves que o `useForm` do Edit.tsx manda sempre (o mesmo envio do `payloadDaTelaReact` do
 * ProdutoEditPayloadContratoTest). Sem uma delas o `update()` aborta antes do save (medido no CI em
 * 2026-11-03: `Undefined array key "brand_id"`), e um caso que espera "nada gravou" ficaria verde no vácuo.
 */
function pedit15Payload(Product $produto, array $extra = []): array
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

function pedit15Preco(int $variacaoId, float $compra, float $margem, float $venda): void
{
    Variation::whereKey($variacaoId)->update([
        'default_purchase_price' => $compra, 'dpp_inc_tax' => $compra, 'profit_percent' => $margem,
        'default_sell_price' => $venda, 'sell_price_inc_tax' => $venda,
    ]);
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

it('UC-PEDIT-16 · a tela Editar recebe o preço gravado do produto simples', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $variacaoId = $p->variations[0]['variation_id'];
    pedit15Preco($variacaoId, 10.1234, 25, 12.6543);

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resp = $this->get("/products/{$p->productId}/edit", [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
    ]);
    $resp->assertOk();

    expect($resp->json('props.product.precoSimples'))->toEqual([
        'variacaoId' => $variacaoId, 'compra' => 10.1234, 'margem' => 25.0, 'venda' => 12.6543,
    ]);
});

it('UC-PEDIT-16 · mexer no preço grava os cinco campos na variação do produto, sem inflar', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $variacaoId = $p->variations[0]['variation_id'];
    pedit15Preco($variacaoId, 10, 25, 12.5);
    $produto = Product::findOrFail($p->productId);

    // Antes → depois: venda 12,50 → 1.234,56 (o envio leva "1234,56", sem milhar).
    $this->put("/products/{$p->productId}", pedit15Payload($produto, [
        'single_variation_id' => $variacaoId,
        'single_dpp' => '10', 'single_dpp_inc_tax' => '10', 'profit_percent' => '12245,6',
        'single_dsp' => '1234,56', 'single_dsp_inc_tax' => '1234,56',
    ]));

    $v = Variation::findOrFail($variacaoId);
    expect((float) $v->default_purchase_price)->toBe(10.0);
    expect((float) $v->default_sell_price)->toBe(1234.56);
    expect((float) $v->sell_price_inc_tax)->toBe(1234.56);
    expect((float) $v->profit_percent)->toBe(12245.6);
});

it('UC-PEDIT-16 · sem campos de preço no envio, o preço gravado fica como está', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $variacaoId = $p->variations[0]['variation_id'];
    pedit15Preco($variacaoId, 10.1234, 25, 12.6543);
    $produto = Product::findOrFail($p->productId);

    $this->put("/products/{$p->productId}", pedit15Payload($produto, ['name' => $produto->name . ' (editado)']));

    // anti-vácuo: o PUT persistiu (o nome mudou), então "o preço ficou" é preservação, não ausência de escrita.
    expect(Product::findOrFail($p->productId)->name)->toBe($produto->name . ' (editado)');
    $v = Variation::findOrFail($variacaoId);
    expect((float) $v->default_purchase_price)->toBe(10.1234);
    expect((float) $v->default_sell_price)->toBe(12.6543);
});

it('UC-PEDIT-16 · variação de produto de outra empresa não tem o preço regravado [T0]', function () {
    $meu = EstoqueFixture::singleProduct($this->business->id);
    $alheio = EstoqueFixture::singleProduct($this->vizinho->id);
    $variacaoAlheia = $alheio->variations[0]['variation_id'];
    pedit15Preco($variacaoAlheia, 50, 20, 60);
    $produto = Product::findOrFail($meu->productId);

    // Controle positivo: o MESMO envio, com a variação do próprio produto, chega ao save. Sem isto,
    // "o preço alheio ficou" não distingue a trava de um envio que nem chega a gravar.
    $this->put("/products/{$meu->productId}", pedit15Payload($produto, [
        'name' => $produto->name . ' (controle)',
        'single_variation_id' => $meu->variations[0]['variation_id'],
        'single_dpp' => '2', 'single_dpp_inc_tax' => '2', 'profit_percent' => '0',
        'single_dsp' => '2', 'single_dsp_inc_tax' => '2',
    ]));
    expect(Product::findOrFail($meu->productId)->name)->toBe($produto->name . ' (controle)');
    expect((float) Variation::findOrFail($meu->variations[0]['variation_id'])->default_sell_price)->toBe(2.0);

    $this->put("/products/{$meu->productId}", pedit15Payload($produto, [
        'single_variation_id' => $variacaoAlheia,
        'single_dpp' => '1', 'single_dpp_inc_tax' => '1', 'profit_percent' => '0',
        'single_dsp' => '1', 'single_dsp_inc_tax' => '1',
    ]));

    $v = Variation::findOrFail($variacaoAlheia);
    expect((float) $v->default_purchase_price)->toBe(50.0);
    expect((float) $v->default_sell_price)->toBe(60.0);
});
