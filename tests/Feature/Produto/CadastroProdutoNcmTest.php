<?php

declare(strict_types=1);

// Cobre UC-PCAD-13 (resources/js/Pages/Produto/Create.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * NCM no cadastro de produto (UC-PCAD-13).
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
 *   · duplicar leva o NCM do produto original para a tela.
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
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo('product.create');
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
