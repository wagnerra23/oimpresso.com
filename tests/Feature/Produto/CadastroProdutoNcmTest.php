<?php

declare(strict_types=1);

// Cobre UC-PCAD-13 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-11
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * NCM no cadastro de produto: Novo (UC-PCAD-13) e Editar (UC-PEDIT-11).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-16 do SDD do cadastro de produto — decisão [M]
 * 2026-10-20: "o NCM entra no cadastro do produto". Regra: 8 dígitos, só números; o cadastro NÃO
 * exige o NCM, porque produto de serviço não tem NCM (Súmula 156 do STJ: serviço gráfico sob
 * encomenda paga ISS e sai em nota de serviço). Sem NCM o produto nasce com o DEFAULT '0' da
 * coluna `products.ncm`.
 *
 * O que cada caso trava:
 *   · grava o NCM de 8 dígitos que a tela manda;
 *   · sem NCM (campo vazio, ou o Blade, que não manda o campo) o produto é criado com '0';
 *   · NCM fora do formato é recusado ANTES do save: nenhum produto nasce;
 *   · duplicar leva o NCM do produto original para a tela;
 *   · editar grava o NCM enviado; vazio volta ao '0'; fora do formato é recusado sem gravar nada;
 *   · editar sem o campo (o Blade, ou a tela React quando a pessoa não mexeu no NCM) não muda o NCM;
 *   · editar o NCM de produto de outra empresa devolve 404 e não grava;
 *   · a tela Editar recebe o NCM gravado.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pcncmCriar(object $test, array $campos): ?object
{
    $bizId = (int) $test->business->id;
    $nome = 'Produto UC-PCAD-13 ' . uniqid();

    $test->post('/products', array_merge([
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ], $campos));

    return DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
}

function pcncmPayloadEdit(object $produto, array $override = []): array
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

function pcncmNcmDoProduto(int $productId): string
{
    return (string) DB::table('products')->where('id', $productId)->value('ncm');
}

function pcncmVisitaInertia(): array
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

it('UC-PCAD-13 · produto novo grava o NCM digitado', function () {
    $produto = pcncmCriar($this, ['ncm' => '49111090']);

    expect($produto)->not->toBeNull();
    expect((string) $produto->ncm)->toBe('49111090');
});

it('UC-PCAD-13 · produto novo sem NCM é salvo, com o NCM vazio do banco', function (array $campos) {
    $produto = pcncmCriar($this, $campos);

    // O cadastro não exige NCM: produto de serviço não tem. O produto nasce com o DEFAULT '0'.
    expect($produto)->not->toBeNull();
    expect((string) $produto->ncm)->toBe('0');
})->with([
    'campo vazio (tela React)' => [['ncm' => '']],
    'sem o campo (cadastro Blade)' => [[]],
]);

it('UC-PCAD-13 · NCM fora do formato é recusado e nenhum produto nasce', function (string $ncm) {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-13 recusado ' . uniqid();

    $this->post('/products', [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
        'ncm' => $ncm,
    ])->assertSessionHasErrors('ncm');

    expect(DB::table('products')->where('business_id', $bizId)->where('name', $nome)->exists())->toBeFalse();
})->with([
    'curto' => ['4911'],
    'com pontos' => ['4911.10.90'],
    'longo' => ['491110900'],
]);

it('UC-PCAD-13 · duplicar leva o NCM do produto original para a tela', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '49111090']);

    // Visita Inertia: a resposta é o JSON da página, e o contrato é a prop que a tela lê.
    $this->withHeaders(pcncmVisitaInertia())
        ->get('/products/create?d=' . $p->productId)
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Create')
        ->assertJsonPath('props.duplicateProduct.id', $p->productId)
        ->assertJsonPath('props.duplicateProduct.ncm', '49111090');
});

// =============================================================================
// UC-PEDIT-11 — editar o NCM
// =============================================================================

it('UC-PEDIT-11 · editar grava o NCM enviado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcncmPayloadEdit($produto, ['ncm' => '49111090']));

    expect(pcncmNcmDoProduto($p->productId))->toBe('49111090');
});

it('UC-PEDIT-11 · editar com o NCM apagado volta para sem NCM', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '49111090']);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcncmPayloadEdit($produto, ['ncm' => '']));

    expect(pcncmNcmDoProduto($p->productId))->toBe('0');
});

it('UC-PEDIT-11 · editar sem o campo NCM não muda o NCM gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '4911.10.90']);
    $produto = DB::table('products')->find($p->productId);
    $novoNome = $produto->name . ' (sem NCM no envio)';

    // O Blade não manda o campo; a tela React também não, quando a pessoa não mexeu no NCM.
    $this->put('/products/' . $p->productId, pcncmPayloadEdit($produto, ['name' => $novoNome]));

    // Pré-condição anti-vácuo: o PUT gravou (senão "o NCM ficou" não prova nada).
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($novoNome);
    // O NCM antigo fica como estava, mesmo fora do formato: a edição de outro campo não o barra.
    expect(pcncmNcmDoProduto($p->productId))->toBe('4911.10.90');
});

it('UC-PEDIT-11 · editar com NCM fora do formato é recusado e nada é gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '49111090']);
    $produto = DB::table('products')->find($p->productId);

    $this->put('/products/' . $p->productId, pcncmPayloadEdit($produto, [
        'name' => $produto->name . ' (não pode gravar)', 'ncm' => '4911',
    ]))->assertSessionHasErrors('ncm');

    // A recusa vem da validação ANTES do save: nem o NCM nem o nome mudaram.
    expect(pcncmNcmDoProduto($p->productId))->toBe('49111090');
    expect(DB::table('products')->where('id', $p->productId)->value('name'))->toBe($produto->name);
});

it('UC-PEDIT-11 · editar o NCM de produto de outra empresa devolve 404 e não grava', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);
    $antes = pcncmNcmDoProduto($alheio->productId);
    $produto = DB::table('products')->find($alheio->productId);

    $this->put('/products/' . $alheio->productId, pcncmPayloadEdit($produto, ['ncm' => '49111090']))
        ->assertStatus(404);

    expect(pcncmNcmDoProduto($alheio->productId))->toBe($antes);
});

it('UC-PEDIT-11 · a tela Editar recebe o NCM gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => '49111090']);

    $this->withHeaders(pcncmVisitaInertia())
        ->get('/products/' . $p->productId . '/edit')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Edit')
        ->assertJsonPath('props.product.id', $p->productId)
        ->assertJsonPath('props.product.ncm', '49111090');
});
