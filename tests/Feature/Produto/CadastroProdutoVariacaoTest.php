<?php

declare(strict_types=1);

// Cobre UC-PCAD-32 e UC-PCAD-33 (resources/js/Pages/Produto/Create.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Grade do produto variável no Novo produto: o servidor (UC-PCAD-32).
 *
 * ÂNCORA (contrato, não implementação): decisão do [F] em 2026-11-17 (SDD §10.6): a variação usa o
 * modelo da empresa (Cadastros → Variações), uma variação por produto. Paridade com o
 * `variable_product_form_part.blade.php` (modelo obrigatório, valores do modelo) e com
 * `ProductUtil::createVariableProductVariations` (grava o modelo, os valores e os cinco preços).
 * Tier 0 (ADR 0093): o `createVariableProductVariations` acha o modelo por `find()`, sem empresa.
 *
 * O que cada caso trava:
 *   · o Novo recebe os modelos da empresa com os valores, e nenhum de outra empresa [T0];
 *   · com modelo e valores da empresa o produto nasce variável, com os valores e os preços enviados;
 *   · modelo de outra empresa, valor de outro modelo, sem modelo ou sem valor: recusado, nada nasce.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pcvarVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

/** @return array{0: int, 1: list<int>} id do modelo e ids dos valores */
function pcvarModelo(int $biz, string $nome, array $valores): array
{
    $modelo = (int) DB::table('variation_templates')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];
    foreach ($valores as $valor) {
        $ids[] = (int) DB::table('variation_value_templates')->insertGetId([
            'name' => $valor, 'variation_template_id' => $modelo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return [$modelo, $ids];
}

function pcvarPreco(): array
{
    // Valores como a tela manda (vírgula decimal, sem milhar), com 18% de imposto: 10 + 18% = 11,80;
    // 10 + 25% = 12,50; 12,50 + 18% = 14,75.
    return [
        'default_purchase_price' => '10,00', 'dpp_inc_tax' => '11,80', 'profit_percent' => '25,00',
        'default_sell_price' => '12,50', 'sell_price_inc_tax' => '14,75',
    ];
}

function pcvarPedido(int $biz, string $nome, array $linha): array
{
    return [
        'name' => $nome, 'sku' => '', 'type' => 'variable', 'sku_type' => 'with_out_variation',
        'unit_id' => EstoqueFixture::unitId($biz), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        'product_variation' => [0 => $linha],
    ];
}

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
    $biz = (int) $this->business->id;
    [$this->modelo, $this->valores] = pcvarModelo($biz, 'PCVAR Cor', ['Azul', 'Verde']);

    $this->actingAs($this->user);
    session([
        'user.business_id' => $biz, 'user.id' => (int) $this->user->id, 'business.id' => $biz,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.create']);
});

it('UC-PCAD-32 · o Novo recebe os modelos de variação da empresa com os valores', function () {
    $r = $this->withHeaders(pcvarVisitaInertia())->get('/products/create')->assertOk()->assertJsonPath('component', 'Produto/Create');

    $modelo = collect((array) $r->json('props.modelosVariacao'))->firstWhere('id', $this->modelo);
    expect($modelo)->toBe([
        'id' => $this->modelo, 'nome' => 'PCVAR Cor',
        'valores' => [['id' => $this->valores[0], 'nome' => 'Azul'], ['id' => $this->valores[1], 'nome' => 'Verde']],
    ]);
});

it('UC-PCAD-32 · modelo de variação de outra empresa não entra na lista [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === (int) $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    [$alheio] = pcvarModelo($outra, 'PCVAR Alheio', ['X']);

    $r = $this->withHeaders(pcvarVisitaInertia())->get('/products/create')->assertOk();
    expect(array_column((array) $r->json('props.modelosVariacao'), 'id'))->toContain($this->modelo)->not->toContain($alheio);
});

it('UC-PCAD-32 · com modelo e valores da empresa o produto nasce variável com os valores e os preços enviados', function () {
    $biz = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-32 ' . uniqid();
    $linha = ['variation_template_id' => $this->modelo, 'variations' => [
        array_merge(['value' => 'Azul', 'variation_value_id' => $this->valores[0], 'sub_sku' => ''], pcvarPreco()),
        array_merge(['value' => 'Amarelo', 'variation_value_id' => '', 'sub_sku' => ''], pcvarPreco()),
    ]];

    $this->post('/products', pcvarPedido($biz, $nome, $linha))->assertSessionHasNoErrors();

    $produto = DB::table('products')->where('business_id', $biz)->where('name', $nome)->first();
    expect($produto)->not->toBeNull();
    expect($produto->type)->toBe('variable');
    $grade = DB::table('product_variations')->where('product_id', $produto->id)->get();
    expect($grade)->toHaveCount(1);
    expect((int) $grade[0]->variation_template_id)->toBe($this->modelo);
    expect($grade[0]->name)->toBe('PCVAR Cor');

    $variacoes = DB::table('variations')->where('product_id', $produto->id)->orderBy('id')->get();
    expect($variacoes->pluck('name')->all())->toBe(['Azul', 'Amarelo']);
    foreach ($variacoes as $v) {
        expect([(float) $v->default_purchase_price, (float) $v->dpp_inc_tax, (float) $v->profit_percent, (float) $v->default_sell_price, (float) $v->sell_price_inc_tax])
            ->toBe([10.0, 11.8, 25.0, 12.5, 14.75]);
        expect((string) $v->sub_sku)->not->toBe('');
    }
    // O valor novo ("Amarelo") entra no modelo da empresa, como no Blade.
    expect(DB::table('variation_value_templates')->where('variation_template_id', $this->modelo)->where('name', 'Amarelo')->exists())->toBeTrue();
});

it('UC-PCAD-32 · grade recusada não cria produto', function (string $caso) {
    $biz = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-32 recusado ' . uniqid();
    $valor = array_merge(['value' => 'Azul', 'variation_value_id' => $this->valores[0], 'sub_sku' => ''], pcvarPreco());
    $outroModelo = pcvarModelo($biz, 'PCVAR Tamanho', ['P'])[1][0];
    $outra = EstoqueFixture::secondBusinessId();
    if ($caso === 'modelo de outra empresa' && ($outra === null || $outra === $biz)) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }

    [$linha, $mensagem] = match ($caso) {
        'modelo de outra empresa' => [
            ['variation_template_id' => pcvarModelo((int) $outra, 'PCVAR Alheio', [])[0], 'variations' => [$valor]],
            'O modelo de variação não pertence a este negócio.',
        ],
        'valor de outro modelo' => [
            ['variation_template_id' => $this->modelo, 'variations' => [array_merge($valor, ['variation_value_id' => $outroModelo])]],
            'O valor escolhido não é deste modelo de variação.',
        ],
        'sem modelo' => [['variation_template_id' => '', 'variations' => [$valor]], 'Escolha o modelo da variação.'],
        'sem valor' => [['variation_template_id' => $this->modelo, 'variations' => [array_merge($valor, ['value' => ''])]], 'Adicione ao menos um valor da variação.'],
    };

    $this->post('/products', pcvarPedido($biz, $nome, $linha))->assertSessionHasErrors(['product_variation' => $mensagem]);

    expect(DB::table('products')->where('business_id', $biz)->where('name', $nome)->exists())->toBeFalse();
})->with(['modelo de outra empresa', 'valor de outro modelo', 'sem modelo', 'sem valor']);

// UC-PCAD-33: o sub-SKU de cada valor pode ser digitado, como no Blade (o código do fornecedor, por
// exemplo). Âncora: product_variation_row.blade.php (`sub_sku` editável) + createVariableProductVariations
// (`sub_sku` vazio → generateSubSku) + product.js, que chama `validateVaritionSkus` antes de enviar.
// O `store()` não conferia; agora confere, só nos SKUs desta empresa [T0].
it('UC-PCAD-33 · o sub-SKU digitado é gravado; o em branco o servidor gera', function () {
    $biz = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-33 ' . uniqid();
    $digitado = 'PCVAR' . strtoupper(substr(uniqid(), -8));
    $linha = ['variation_template_id' => $this->modelo, 'variations' => [
        array_merge(['value' => 'Azul', 'variation_value_id' => $this->valores[0], 'sub_sku' => " {$digitado} "], pcvarPreco()),
        array_merge(['value' => 'Verde', 'variation_value_id' => $this->valores[1], 'sub_sku' => ''], pcvarPreco()),
    ]];

    $this->post('/products', pcvarPedido($biz, $nome, $linha))->assertSessionHasNoErrors();

    $produto = DB::table('products')->where('business_id', $biz)->where('name', $nome)->first();
    $subs = DB::table('variations')->where('product_id', $produto->id)->orderBy('id')->pluck('sub_sku')->all();
    expect($subs[0])->toBe($digitado);
    expect((string) $subs[1])->not->toBe('')->not->toBe($digitado);
});

it('UC-PCAD-33 · sub-SKU repetido é recusado e nada nasce', function (string $caso) {
    $biz = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-33 recusado ' . uniqid();
    $sku = $caso === 'já é SKU de outro produto da empresa'
        ? (string) DB::table('products')->where('id', EstoqueFixture::singleProduct($biz, false)->productId)->value('sku')
        : 'PCVAR' . strtoupper(substr(uniqid(), -8));
    $segundo = $caso === 'repetido na grade' ? $sku : '';
    $linha = ['variation_template_id' => $this->modelo, 'variations' => [
        array_merge(['value' => 'Azul', 'variation_value_id' => $this->valores[0], 'sub_sku' => $sku], pcvarPreco()),
        array_merge(['value' => 'Verde', 'variation_value_id' => $this->valores[1], 'sub_sku' => $segundo], pcvarPreco()),
    ]];
    $mensagem = $caso === 'repetido na grade'
        ? "O SKU {$sku} aparece em mais de um valor da variação."
        : "O SKU {$sku} já existe em outro produto.";

    $this->post('/products', pcvarPedido($biz, $nome, $linha))->assertSessionHasErrors(['product_variation' => $mensagem]);

    expect(DB::table('products')->where('business_id', $biz)->where('name', $nome)->exists())->toBeFalse();
})->with(['repetido na grade', 'já é SKU de outro produto da empresa']);

it('UC-PCAD-33 · o mesmo SKU em produto de outra empresa não impede [T0]', function () {
    $biz = (int) $this->business->id;
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === $biz) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    // O produto da outra empresa tem o mesmo texto como SKU e como sub-SKU da variação.
    $sku = (string) DB::table('products')->where('id', EstoqueFixture::singleProduct($outra, false)->productId)->value('sku');
    $nome = 'Produto UC-PCAD-33 isolado ' . uniqid();
    $linha = ['variation_template_id' => $this->modelo, 'variations' => [
        array_merge(['value' => 'Azul', 'variation_value_id' => $this->valores[0], 'sub_sku' => $sku], pcvarPreco()),
    ]];

    $this->post('/products', pcvarPedido($biz, $nome, $linha))->assertSessionHasNoErrors();

    expect(DB::table('products')->where('business_id', $biz)->where('name', $nome)->exists())->toBeTrue();
});
