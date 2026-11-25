<?php

declare(strict_types=1);

// Cobre UC-PEDIT-35 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Product;
use App\SellingPriceGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Os dois "Salvar e adicionar …" do rodapé do Editar React (UC-PEDIT-35), como no edit.blade.php.
 *
 * - "Salvar e adicionar estoque inicial" manda `submit_type=update_n_edit_opening_stock`. O destino
 *   (OpeningStockController@add) é Blade: num envio Inertia o update() devolve Inertia::location (409
 *   com o cabeçalho X-Inertia-Location), e o navegador abre a página inteira. O envio pelo Blade segue
 *   com o redirect 302 de sempre.
 * - "Salvar e adicionar preços por grupo" só aparece com grupo cadastrado: o edit() passa a contagem
 *   (`sellingPriceGroupCount`) para a tela.
 *
 * Tenant: o seeded do CI (98), nunca biz=4 (ADR 0358).
 */
uses(DatabaseTransactions::class);

function peditTrintaCincoEnvio(Product $produto, string $submitType): array
{
    $campos = [];
    for ($i = 1; $i <= 20; $i++) {
        $campos['product_custom_field' . $i] = $produto->{'product_custom_field' . $i} ?? '';
    }

    return $campos + [
        'expiry_period' => $produto->expiry_period !== null ? (string) (float) $produto->expiry_period : '',
        'expiry_period_type' => $produto->expiry_period_type ?? '',
        'name' => $produto->name . ' (UC-PEDIT-35)',
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
        'submit_type' => $submitType,
    ];
}

function peditTrintaCincoInertia(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();

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

it('UC-PEDIT-35 · "Salvar e adicionar estoque inicial" pelo Editar React salva e abre o estoque inicial (Inertia::location)', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);
    $produto = Product::findOrFail($p->productId);
    $envio = peditTrintaCincoEnvio($produto, 'update_n_edit_opening_stock');

    $resposta = $this->put("/products/{$p->productId}", $envio, peditTrintaCincoInertia());

    expect(Product::findOrFail($p->productId)->name)->toBe(
        $envio['name'],
        'PRÉ-CONDIÇÃO: o produto não foi salvo, então o destino não foi exercido.'
    );
    expect($resposta->status())->toBe(409);
    expect((string) $resposta->headers->get('X-Inertia-Location'))->toEndWith('/opening-stock/add/' . $p->productId);
});

it('UC-PEDIT-35 · o mesmo envio pelo Blade segue com o redirect 302 para o estoque inicial', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);
    $produto = Product::findOrFail($p->productId);
    $envio = peditTrintaCincoEnvio($produto, 'update_n_edit_opening_stock');

    $resposta = $this->put("/products/{$p->productId}", $envio);

    expect(Product::findOrFail($p->productId)->name)->toBe(
        $envio['name'],
        'PRÉ-CONDIÇÃO: o produto não foi salvo, então o destino não foi exercido.'
    );
    $resposta->assertRedirect('/opening-stock/add/' . $p->productId);
});

it('UC-PEDIT-35 · o Editar React recebe quantos grupos de preço a empresa tem', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);

    $resposta = $this->get("/products/{$p->productId}/edit", peditTrintaCincoInertia());

    $resposta->assertOk();
    expect($resposta->json('component'))->toBe('Produto/Edit');
    expect($resposta->json('props.sellingPriceGroupCount'))->toBe(
        (int) SellingPriceGroup::countSellingPriceGroups($this->business->id)
    );
});
