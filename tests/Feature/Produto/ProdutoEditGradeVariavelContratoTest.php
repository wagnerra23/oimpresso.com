<?php

declare(strict_types=1);
// Cobre UC-PEDIT-32 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste (ADR 0264).

use App\Product;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Grade do produto variável na tela Editar React (`GET /products/{id}/edit` Inertia + `PUT /products/{id}`).
 *
 * ÂNCORA: Pendências do Cadastro de produto, item 3 (medido em produção em 2026-11-24: o Editar novo
 * do variável não tinha as variações nem os preços) + REGRA MESTRE de valor (proibicoes.md) + ADR 0093.
 * O envio é o que `Edit.tsx` manda quando a pessoa mexe numa linha: `variacoes_editadas[<id>]` com os
 * cinco preços em vírgula decimal e sem milhar (precos.ts `paraNumUf`) e o sub-SKU.
 *
 * ⛔ Tenant 98 (ADR 0358) contra o vizinho fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
uses(DatabaseTransactions::class);

/** As chaves que o `useForm` do Edit.tsx manda sempre (as mesmas do ProdutoEditPrecoContratoTest). */
function pedit31Payload(Product $produto, array $extra = []): array
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

function pedit31Preco(int $variacaoId, float $compra, float $margem, float $venda): void
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

it('UC-PEDIT-32 · a tela Editar recebe a grade do variável, uma linha por variação, com o preço gravado', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);
    [$v1, $v2] = [$p->variations[0]['variation_id'], $p->variations[1]['variation_id']];
    pedit31Preco($v1, 10.1234, 25, 12.6543);
    pedit31Preco($v2, 20, 50, 30);

    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resp = $this->get("/products/{$p->productId}/edit", [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
    ]);
    $resp->assertOk();

    $linhas = $resp->json('props.product.gradeVariavel.linhas');
    expect($linhas)->toHaveCount(2);
    expect($linhas[0])->toMatchArray(['variacaoId' => $v1, 'valor' => 'Var 1', 'compra' => 10.1234, 'margem' => 25.0, 'venda' => 12.6543]);
    expect($linhas[1])->toMatchArray(['variacaoId' => $v2, 'valor' => 'Var 2', 'compra' => 20.0, 'margem' => 50.0, 'venda' => 30.0]);
    expect($linhas[0]['subSku'])->toEndWith('-1');
});

it('UC-PEDIT-32 · a tela Editar de produto simples não recebe grade', function () {
    $p = EstoqueFixture::singleProduct($this->business->id);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resp = $this->get("/products/{$p->productId}/edit", [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
    ]);
    $resp->assertOk();
    expect($resp->json('props.product.gradeVariavel'))->toBeNull();
});

it('UC-PEDIT-32 · a linha mexida grava os cinco preços e o sub-SKU, e a outra fica como estava', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);
    [$v1, $v2] = [$p->variations[0]['variation_id'], $p->variations[1]['variation_id']];
    pedit31Preco($v1, 10, 25, 12.5);
    pedit31Preco($v2, 20.1234, 50, 30.1851);
    $produto = Product::findOrFail($p->productId);
    $variaveisAntes = Variation::where('product_id', $p->productId)->count();

    // Antes → depois da linha 1: venda 12,50 → 1.234,56 (o envio leva "1234,56", sem milhar).
    $this->put("/products/{$p->productId}", pedit31Payload($produto, [
        'variacoes_editadas' => [
            $v1 => [
                'dpp' => '10', 'dpp_inc_tax' => '11,8', 'profit_percent' => '12245,6',
                'dsp' => '1234,56', 'dsp_inc_tax' => '1456,78', 'sub_sku' => 'FORN-77',
            ],
        ],
    ]));

    $a = Variation::findOrFail($v1);
    expect((float) $a->default_purchase_price)->toBe(10.0);
    expect((float) $a->dpp_inc_tax)->toBe(11.8);
    expect((float) $a->profit_percent)->toBe(12245.6);
    expect((float) $a->default_sell_price)->toBe(1234.56);
    expect((float) $a->sell_price_inc_tax)->toBe(1456.78);
    expect($a->sub_sku)->toBe('FORN-77');

    // A linha que não veio fica com as 4 casas e nenhuma variação é apagada.
    $b = Variation::findOrFail($v2);
    expect((float) $b->default_purchase_price)->toBe(20.1234);
    expect((float) $b->default_sell_price)->toBe(30.1851);
    expect(Variation::where('product_id', $p->productId)->count())->toBe($variaveisAntes);
});

it('UC-PEDIT-32 · sem grade no envio, os preços das variações ficam como estão', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);
    $v1 = $p->variations[0]['variation_id'];
    pedit31Preco($v1, 10.1234, 25, 12.6543);
    $produto = Product::findOrFail($p->productId);

    $this->put("/products/{$p->productId}", pedit31Payload($produto, ['name' => $produto->name . ' (editado)']));

    // anti-vácuo: o PUT persistiu (o nome mudou), então "o preço ficou" é preservação, não ausência de escrita.
    expect(Product::findOrFail($p->productId)->name)->toBe($produto->name . ' (editado)');
    expect((float) Variation::findOrFail($v1)->default_sell_price)->toBe(12.6543);
    expect(Variation::where('product_id', $p->productId)->count())->toBe(2);
});

it('UC-PEDIT-32 · variação de produto de outra empresa não tem o preço regravado [T0]', function () {
    $meu = EstoqueFixture::variableProduct($this->business->id, 1);
    $alheio = EstoqueFixture::variableProduct($this->vizinho->id, 1);
    $minha = $meu->variations[0]['variation_id'];
    $alheia = $alheio->variations[0]['variation_id'];
    pedit31Preco($alheia, 50, 20, 60);
    $produto = Product::findOrFail($meu->productId);
    $linha = ['dpp' => '1', 'dpp_inc_tax' => '1', 'profit_percent' => '0', 'dsp' => '1', 'dsp_inc_tax' => '1', 'sub_sku' => 'INVASOR'];

    // Controle positivo: o MESMO envio, com a variação do próprio produto, chega ao save.
    $this->put("/products/{$meu->productId}", pedit31Payload($produto, [
        'name' => $produto->name . ' (controle)',
        'variacoes_editadas' => [$minha => $linha],
    ]));
    expect(Product::findOrFail($meu->productId)->name)->toBe($produto->name . ' (controle)');
    expect((float) Variation::findOrFail($minha)->default_sell_price)->toBe(1.0);

    $this->put("/products/{$meu->productId}", pedit31Payload($produto, [
        'name' => $produto->name . ' (invasão)',
        'variacoes_editadas' => [$alheia => $linha],
    ]));

    $v = Variation::findOrFail($alheia);
    expect((float) $v->default_purchase_price)->toBe(50.0);
    expect((float) $v->default_sell_price)->toBe(60.0);
    expect($v->sub_sku)->not->toBe('INVASOR');
    // O update inteiro voltou: nem o nome do próprio produto mudou.
    expect(Product::findOrFail($meu->productId)->name)->toBe($produto->name . ' (controle)');
});
