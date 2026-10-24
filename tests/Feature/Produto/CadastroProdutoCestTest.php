<?php

declare(strict_types=1);

// Cobre UC-PCAD-15 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-12
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * CEST no cadastro de produto: Novo (UC-PCAD-15) e Editar (UC-PEDIT-12).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-16 do SDD do cadastro de produto + Convênio ICMS
 * 142/2018, cláusula sexta: o CEST tem 7 dígitos. Só a mercadoria com substituição tributária
 * precisa dele, então o cadastro NÃO o exige. Sem CEST, a coluna `products.cest` fica nula.
 *
 * O que cada caso trava:
 *   · grava o CEST de 7 dígitos que a tela manda;
 *   · sem CEST (campo vazio, ou o Blade, que não manda o campo) o produto é criado com CEST nulo;
 *   · CEST fora do formato é recusado ANTES do save: nenhum produto nasce;
 *   · duplicar leva o CEST do produto original para a tela;
 *   · editar grava o CEST enviado; vazio volta a nulo; fora do formato é recusado sem gravar nada;
 *   · editar sem o campo (o Blade, ou a tela React quando a pessoa não mexeu no CEST) não muda o CEST;
 *   · editar o CEST de produto de outra empresa devolve 404 e não grava;
 *   · a tela Editar recebe o CEST gravado.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pccestCriar(object $test, array $campos): ?object
{
    $bizId = (int) $test->business->id;
    $nome = 'Produto UC-PCAD-15 ' . uniqid();

    $test->post('/products', array_merge([
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ], $campos));

    return DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
}

function pccestPayloadEdit(object $produto, array $override = []): array
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

function pccestDoProduto(int $productId): ?string
{
    $cest = DB::table('products')->where('id', $productId)->value('cest');

    return $cest === null ? null : (string) $cest;
}

function pccestVisitaInertia(): array
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
// UC-PCAD-15 — cadastrar produto com o CEST
// =============================================================================

it('UC-PCAD-15 · produto novo grava o CEST digitado', function () {
    $produto = pccestCriar($this, ['cest' => '2803800']);

    expect($produto)->not->toBeNull();
    expect((string) $produto->cest)->toBe('2803800');
});

it('UC-PCAD-15 · produto novo sem CEST é salvo, com o CEST nulo', function (array $campos) {
    $produto = pccestCriar($this, $campos);

    // O cadastro não exige CEST: só a mercadoria com substituição tributária precisa dele.
    expect($produto)->not->toBeNull();
    expect($produto->cest)->toBeNull();
})->with([
    'campo vazio (tela React)' => [['cest' => '']],
    'sem o campo (cadastro Blade)' => [[]],
]);

it('UC-PCAD-15 · CEST fora do formato é recusado e nenhum produto nasce', function (string $cest) {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-15 recusado ' . uniqid();

    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        'cest' => $cest,
    ])->assertSessionHasErrors('cest');

    expect(DB::table('products')->where('business_id', $bizId)->where('name', $nome)->exists())->toBeFalse();
})->with([
    'curto' => ['28038'],
    'com pontos' => ['28.038.00'],
    'longo' => ['28038000'],
]);

it('UC-PCAD-15 · duplicar leva o CEST do produto original para a tela', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['cest' => '2803800']);

    $this->withHeaders(pccestVisitaInertia())
        ->get('/products/create?d=' . $p->productId)
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Create')
        ->assertJsonPath('props.duplicateProduct.id', $p->productId)
        ->assertJsonPath('props.duplicateProduct.cest', '2803800');
});

// =============================================================================
// UC-PEDIT-12 — editar o CEST
// =============================================================================

it('UC-PEDIT-12 · editar grava o CEST enviado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pccestPayloadEdit($produto, ['cest' => '2803800']));

    expect(pccestDoProduto($p->productId))->toBe('2803800');
});

it('UC-PEDIT-12 · editar com o CEST apagado volta para sem CEST', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['cest' => '2803800']);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pccestPayloadEdit($produto, ['cest' => '']));

    expect(pccestDoProduto($p->productId))->toBeNull();
});

it('UC-PEDIT-12 · editar sem o campo CEST não muda o CEST gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['cest' => '28.038.00']);
    $produto = DB::table('products')->find($p->productId);
    $novoNome = $produto->name . ' (sem CEST no envio)';

    // O Blade não manda o campo; a tela React também não, quando a pessoa não mexeu no CEST.
    $this->put('/products/' . $p->productId, pccestPayloadEdit($produto, ['name' => $novoNome]));

    // Pré-condição anti-vácuo: o PUT gravou (senão "o CEST ficou" não prova nada).
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($novoNome);
    // O CEST antigo fica como estava, mesmo fora do formato: a edição de outro campo não o barra.
    expect(pccestDoProduto($p->productId))->toBe('28.038.00');
});

it('UC-PEDIT-12 · editar com CEST fora do formato é recusado e nada é gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['cest' => '2803800']);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pccestPayloadEdit($produto, [
        'name' => $produto->name . ' (não pode gravar)', 'cest' => '28038',
    ]))->assertSessionHasErrors('cest');

    // A recusa vem da validação ANTES do save: nem o CEST nem o nome mudaram.
    expect(pccestDoProduto($p->productId))->toBe('2803800');
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($produto->name);
});

it('UC-PEDIT-12 · editar o CEST de produto de outra empresa devolve 404 e não grava', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);
    $antes = pccestDoProduto($alheio->productId);
    $produto = DB::table('products')->find($alheio->productId);

    $this->put('/products/' . $alheio->productId, pccestPayloadEdit($produto, ['cest' => '2803800']))
        ->assertStatus(404);

    expect(pccestDoProduto($alheio->productId))->toBe($antes);
});

it('UC-PEDIT-12 · a tela Editar recebe o CEST gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['cest' => '2803800']);

    $this->withHeaders(pccestVisitaInertia())
        ->get('/products/' . $p->productId . '/edit')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.product.id', $p->productId)
        ->assertJsonPath('props.product.cest', '2803800');
});
