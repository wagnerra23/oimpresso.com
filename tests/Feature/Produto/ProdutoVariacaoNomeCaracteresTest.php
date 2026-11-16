<?php

declare(strict_types=1);

use App\Utils\ProductUtil;
use App\VariationTemplate;
use App\VariationValueTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

/**
 * Nome de variação (modelo e valor) com aspas e outros caracteres especiais, no cadastro e na
 * edição de produto variável (`ProductUtil::createVariableProductVariations` /
 * `updateVariableProductVariations`). O nome digitado é DADO: tem que ser comparado como texto,
 * reaproveitando o modelo/valor que já existe com o mesmo nome, sem erro de banco.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->biz = EstoqueFixture::businessId();
    session([
        'user.business_id' => $this->biz,
        'currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
});

function pvncLinha(string $valor, string $sku): array
{
    return [
        'value' => $valor, 'sub_sku' => $sku,
        'default_purchase_price' => '10', 'dpp_inc_tax' => '10', 'profit_percent' => '0',
        'default_sell_price' => '10', 'sell_price_inc_tax' => '10',
    ];
}

it('reaproveita modelo e valor de variação cujo nome tem aspas', function () {
    $modeloNome = 'tam "g" ' . bin2hex(random_bytes(3));
    $valorNome = 'p" or "1"="1';
    $modelo = VariationTemplate::create(['name' => $modeloNome, 'business_id' => $this->biz]);
    VariationValueTemplate::create(['name' => $valorNome, 'variation_template_id' => $modelo->id]);

    $produto = EstoqueFixture::singleProduct($this->biz);
    $sku = 'PVNC-' . strtoupper(bin2hex(random_bytes(3)));

    app(ProductUtil::class)->createVariableProductVariations($produto->productId, [
        ['name' => $modeloNome, 'variations' => [pvncLinha($valorNome, $sku)]],
    ], 'with_out_variation', $this->biz);

    expect(VariationTemplate::where('business_id', $this->biz)->where('name', $modeloNome)->count())->toBe(1);
    expect(VariationValueTemplate::where('variation_template_id', $modelo->id)->count())->toBe(1);
    $variacao = \App\Variation::where('sub_sku', $sku)->first();
    expect($variacao)->not->toBeNull();
    expect($variacao->name)->toBe($valorNome);
    expect((int) $variacao->variation_value_id)->toBe((int) $modelo->values()->first()->id);
});

it('cria valor novo com aspas na edição sem duplicar o existente (controle)', function () {
    $modeloNome = 'cor ' . bin2hex(random_bytes(3));
    $produto = EstoqueFixture::singleProduct($this->biz);
    $util = app(ProductUtil::class);

    $util->createVariableProductVariations($produto->productId, [
        ['name' => $modeloNome, 'variations' => [pvncLinha('azul', 'PVNC-A-' . bin2hex(random_bytes(3)))]],
    ], 'with_out_variation', $this->biz);

    $modelo = VariationTemplate::where('business_id', $this->biz)->where('name', $modeloNome)->firstOrFail();
    $pv = \App\ProductVariation::where('product_id', $produto->productId)
        ->where('variation_template_id', $modelo->id)->firstOrFail();
    $existente = \App\Variation::where('product_variation_id', $pv->id)->firstOrFail();

    $novoValor = 'verde "musgo"';
    $util->updateVariableProductVariations($produto->productId, [
        $pv->id => [
            'name' => $modeloNome,
            'variations_edit' => [$existente->id => pvncLinha('azul', $existente->sub_sku)],
            'variations' => [pvncLinha($novoValor, 'PVNC-V-' . bin2hex(random_bytes(3)))],
        ],
    ], 'with_out_variation');

    expect(VariationValueTemplate::where('variation_template_id', $modelo->id)->where('name', $novoValor)->count())->toBe(1);
    expect(VariationValueTemplate::where('variation_template_id', $modelo->id)->where('name', 'azul')->count())->toBe(1);
});
