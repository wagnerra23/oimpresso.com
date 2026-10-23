<?php

declare(strict_types=1);

// Cobre UC-PCAD-16 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-13
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Peso líquido e dimensões da embalagem no cadastro de produto: Novo (UC-PCAD-16) e Editar
 * (UC-PEDIT-13).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-17 do SDD do cadastro de produto (peso e dimensões
 * para frete e marketplace) — pedido [M] 2026-10-21. O "Peso" que já existia (`weight`) passa a ser
 * mostrado como peso bruto e não muda aqui. Os quatro campos novos são opcionais, números de 0 a
 * 99999: peso líquido em kg, largura, altura e comprimento da embalagem em cm.
 *
 * O que cada caso trava:
 *   · grava o peso líquido e as dimensões que a tela manda;
 *   · sem os campos (vazios, ou o Blade, que não manda nenhum) o produto é criado com os quatro nulos;
 *   · número negativo ou texto é recusado ANTES do save: nenhum produto nasce;
 *   · duplicar leva o peso líquido e as dimensões do produto original para a tela;
 *   · editar grava; campo vazio volta a nulo; valor inválido é recusado sem gravar nada;
 *   · editar pelo Blade (sem os campos) não muda o que está gravado;
 *   · editar produto de outra empresa devolve 404 e não grava;
 *   · a tela Editar recebe o peso líquido e as dimensões gravados.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

/** Os quatro campos com valores de exemplo (caixa de 30 × 20,5 × 10 cm, 1,25 kg). */
function pcpdValores(): array
{
    return ['peso_liquido' => '1.25', 'largura_cm' => '30', 'altura_cm' => '20.5', 'comprimento_cm' => '10'];
}

/** Os quatro campos do produto como float (ou null), lidos do banco. */
function pcpdDoProduto(int $productId): array
{
    $linha = DB::table('products')->where('id', $productId)
        ->first(['peso_liquido', 'largura_cm', 'altura_cm', 'comprimento_cm']);

    return array_map(fn ($v) => $v === null ? null : (float) $v, (array) $linha);
}

function pcpdCriar(object $test, array $campos): ?object
{
    $bizId = (int) $test->business->id;
    $nome = 'Produto UC-PCAD-16 ' . uniqid();

    $test->post('/products', array_merge([
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ], $campos));

    return DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
}

function pcpdPayloadEdit(object $produto, array $override = []): array
{
    return array_merge([
        'name' => $produto->name,
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'preparation_time_in_minutes' => '',
    ], $override);
}

function pcpdVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
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

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
        'business.id' => $this->business->id,
        // O edit() formata número com a moeda da sessão (mesmo setup do ProdutoEditPayloadContratoTest).
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

// =============================================================================
// UC-PCAD-16 — cadastrar produto com peso líquido e dimensões
// =============================================================================

it('UC-PCAD-16 · produto novo grava o peso líquido e as dimensões', function () {
    $produto = pcpdCriar($this, pcpdValores());

    expect($produto)->not->toBeNull();
    expect(pcpdDoProduto((int) $produto->id))->toBe([
        'peso_liquido' => 1.25, 'largura_cm' => 30.0, 'altura_cm' => 20.5, 'comprimento_cm' => 10.0,
    ]);
});

it('UC-PCAD-16 · produto novo sem peso líquido e dimensões é salvo, com os quatro nulos', function (array $campos) {
    $produto = pcpdCriar($this, $campos);

    expect($produto)->not->toBeNull();
    expect(pcpdDoProduto((int) $produto->id))->toBe([
        'peso_liquido' => null, 'largura_cm' => null, 'altura_cm' => null, 'comprimento_cm' => null,
    ]);
})->with([
    'campos vazios (tela React)' => [['peso_liquido' => '', 'largura_cm' => '', 'altura_cm' => '', 'comprimento_cm' => '']],
    'sem os campos (cadastro Blade)' => [[]],
]);

it('UC-PCAD-16 · valor inválido é recusado e nenhum produto nasce', function (string $campo, string $valor) {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-16 recusado ' . uniqid();

    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        $campo => $valor,
    ])->assertSessionHasErrors($campo);

    expect(DB::table('products')->where('business_id', $bizId)->where('name', $nome)->exists())->toBeFalse();
})->with([
    'peso líquido negativo' => ['peso_liquido', '-1'],
    'largura em texto' => ['largura_cm', 'trinta'],
    'altura acima do limite' => ['altura_cm', '100000'],
]);

it('UC-PCAD-16 · duplicar leva o peso líquido e as dimensões do produto original para a tela', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(pcpdValores());

    $resposta = $this->withHeaders(pcpdVisitaInertia())
        ->get('/products/create?d=' . $p->productId)
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Create')
        ->assertJsonPath('props.duplicateProduct.id', $p->productId);

    expect((float) $resposta->json('props.duplicateProduct.peso_liquido'))->toBe(1.25);
    expect((float) $resposta->json('props.duplicateProduct.largura_cm'))->toBe(30.0);
    expect((float) $resposta->json('props.duplicateProduct.altura_cm'))->toBe(20.5);
    expect((float) $resposta->json('props.duplicateProduct.comprimento_cm'))->toBe(10.0);
});

// =============================================================================
// UC-PEDIT-13 — editar peso líquido e dimensões
// =============================================================================

it('UC-PEDIT-13 · editar grava o peso líquido e as dimensões', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcpdPayloadEdit($produto, pcpdValores()));

    expect(pcpdDoProduto($p->productId))->toBe([
        'peso_liquido' => 1.25, 'largura_cm' => 30.0, 'altura_cm' => 20.5, 'comprimento_cm' => 10.0,
    ]);
});

it('UC-PEDIT-13 · editar com um campo apagado volta esse campo a nulo', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(pcpdValores());
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcpdPayloadEdit($produto, array_merge(pcpdValores(), ['altura_cm' => ''])));

    expect(pcpdDoProduto($p->productId))->toBe([
        'peso_liquido' => 1.25, 'largura_cm' => 30.0, 'altura_cm' => null, 'comprimento_cm' => 10.0,
    ]);
});

it('UC-PEDIT-13 · editar pelo Blade (sem os campos) não muda o que está gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(pcpdValores());
    $produto = DB::table('products')->find($p->productId);
    $novoNome = $produto->name . ' (sem medidas no envio)';

    $this->put('/products/' . $p->productId, pcpdPayloadEdit($produto, ['name' => $novoNome]));

    // Pré-condição anti-vácuo: o PUT gravou (senão "as medidas ficaram" não prova nada).
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($novoNome);
    expect(pcpdDoProduto($p->productId))->toBe([
        'peso_liquido' => 1.25, 'largura_cm' => 30.0, 'altura_cm' => 20.5, 'comprimento_cm' => 10.0,
    ]);
});

it('UC-PEDIT-13 · editar com valor inválido é recusado e nada é gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(pcpdValores());
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcpdPayloadEdit($produto, [
        'name' => $produto->name . ' (não pode gravar)', 'largura_cm' => '-5',
    ]))->assertSessionHasErrors('largura_cm');

    // A recusa vem da validação ANTES do save: nem a largura nem o nome mudaram.
    expect(pcpdDoProduto($p->productId)['largura_cm'])->toBe(30.0);
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($produto->name);
});

it('UC-PEDIT-13 · editar as medidas de produto de outra empresa devolve 404 e não grava', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);
    $antes = pcpdDoProduto($alheio->productId);
    $produto = DB::table('products')->find($alheio->productId);

    $this->put('/products/' . $alheio->productId, pcpdPayloadEdit($produto, pcpdValores()))
        ->assertStatus(404);

    expect(pcpdDoProduto($alheio->productId))->toBe($antes);
});

it('UC-PEDIT-13 · a tela Editar recebe o peso líquido e as dimensões gravados', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(pcpdValores());

    $resposta = $this->withHeaders(pcpdVisitaInertia())
        ->get('/products/' . $p->productId . '/edit')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.product.id', $p->productId);

    expect((float) $resposta->json('props.product.pesoLiquido'))->toBe(1.25);
    expect((float) $resposta->json('props.product.larguraCm'))->toBe(30.0);
    expect((float) $resposta->json('props.product.alturaCm'))->toBe(20.5);
    expect((float) $resposta->json('props.product.comprimentoCm'))->toBe(10.0);
});
