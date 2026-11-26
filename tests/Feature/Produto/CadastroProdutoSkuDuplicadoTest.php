<?php

declare(strict_types=1);

// Cobre UC-PCAD-34 (resources/js/Pages/Produto/Create.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * SKU digitado repetido é recusado no salvar do produto (UC-PCAD-34).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-01 item 2 do SDD do cadastro de produto —
 * "SKU vazio → gerado server-side; SKU digitado → validado duplicado" — e a paridade com o Blade,
 * que confere o SKU em `/products/check_product_sku` antes de enviar (produto e sub-SKU de
 * variação, só da empresa, ignorando o próprio produto no Editar).
 *
 * O que cada caso trava:
 *   · Novo com SKU que já é de outro produto da empresa: recusado no campo SKU, nada nasce;
 *   · Novo com o SKU de um produto de OUTRA empresa: aceito, o produto nasce com ele [T0];
 *   · Editar trocando para o SKU de outro produto da empresa: recusado, o SKU gravado fica;
 *   · Editar trocando para um SKU livre: grava.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant(); // biz=98 (ADR 0358 — nunca biz=4).
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
        'business.id' => $this->business->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

function pcskuNovo(int $bizId, string $nome, string $sku): array
{
    return [
        'name' => $nome, 'sku' => $sku, 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ];
}

function pcskuDe(int $productId): string
{
    return (string) DB::table('products')->where('id', $productId)->value('sku');
}

function pcskuEditar(int $productId, string $nome, string $sku, int $variacaoId): array
{
    $produto = \App\Product::findOrFail($productId);

    return [
        'name' => $nome, 'sku' => $sku, 'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id, 'category_id' => $produto->category_id ?? '', 'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type, 'barcode_type' => $produto->barcode_type, 'alert_quantity' => '',
        'weight' => '', 'product_description' => '', 'product_locations' => [], 'preparation_time_in_minutes' => '',
        'single_variation_id' => $variacaoId, 'single_dpp' => '10,00', 'single_dpp_inc_tax' => '10,00',
        'profit_percent' => '0', 'single_dsp' => '20,00', 'single_dsp_inc_tax' => '20,00',
    ];
}

it('UC-PCAD-34 · Novo com SKU de outro produto da empresa é recusado e nada nasce', function () {
    $biz = (int) $this->business->id;
    $sku = pcskuDe(EstoqueFixture::singleProduct($biz, false)->productId);
    $nome = 'Produto UC-PCAD-34 repetido ' . uniqid();

    $this->post('/products', pcskuNovo($biz, $nome, $sku))
        ->assertSessionHasErrors(['sku' => "O SKU {$sku} já existe em outro produto."]);

    expect(DB::table('products')->where('business_id', $biz)->where('name', $nome)->exists())->toBeFalse();
});

it('UC-PCAD-34 · Novo com o SKU de um produto de outra empresa é aceito [T0]', function () {
    $biz = (int) $this->business->id;
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === $biz) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $sku = pcskuDe(EstoqueFixture::singleProduct($outra, false)->productId);
    $nome = 'Produto UC-PCAD-34 isolado ' . uniqid();

    $this->post('/products', pcskuNovo($biz, $nome, $sku))->assertSessionHasNoErrors();

    expect(DB::table('products')->where('business_id', $biz)->where('name', $nome)->value('sku'))->toBe($sku);
});

it('UC-PCAD-34 · Editar trocando para o SKU de outro produto da empresa é recusado', function () {
    $biz = (int) $this->business->id;
    $alvo = EstoqueFixture::singleProduct($biz, false);
    $skuAntes = pcskuDe($alvo->productId);
    $skuOutro = pcskuDe(EstoqueFixture::singleProduct($biz, false)->productId);

    $this->put("/products/{$alvo->productId}", pcskuEditar($alvo->productId, 'Editado UC-PCAD-34 ' . uniqid(), $skuOutro, $alvo->variationId(0)))
        ->assertSessionHasErrors(['sku' => "O SKU {$skuOutro} já existe em outro produto."]);

    expect(pcskuDe($alvo->productId))->toBe($skuAntes);
});

it('UC-PCAD-34 · Editar trocando para um SKU livre grava', function () {
    $biz = (int) $this->business->id;
    $alvo = EstoqueFixture::singleProduct($biz, false);
    $novo = 'PCSKU' . strtoupper(substr(uniqid(), -8));
    $nome = 'Editado UC-PCAD-34 livre ' . uniqid();

    $this->put("/products/{$alvo->productId}", pcskuEditar($alvo->productId, $nome, $novo, $alvo->variationId(0)))
        ->assertSessionHasNoErrors();

    // Pré-condição anti-vácuo: o PUT gravou (sem isto o SKU "igual" não distingue gravado de não exercido).
    expect(\App\Product::findOrFail($alvo->productId)->name)->toBe($nome);
    expect(pcskuDe($alvo->productId))->toBe($novo);
});
