<?php

declare(strict_types=1);

// Cobre UC-PCAD-28 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-28
// (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * "+" de unidade e de marca no cadastro de produto (servidor).
 *
 * ÂNCORA (contrato, não implementação): `product/create.blade.php` desabilita o "+" sem `unit.create`
 * e sem `brand.create`; o protótipo do [W] (`produto-blade-forms.jsx`) abre o cadastro rápido em modal;
 * pedido do [F] em 2026-11-16. A tela recebe as duas permissões e grava pelas rotas de sempre
 * (`/units`, `/brands`), que respondem o id do item novo.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pcaxVisitaInertia(): array
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
        'user.business_id' => (int) $this->business->id, 'user.id' => $this->user->id, 'business.id' => (int) $this->business->id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['product.create', 'product.update', 'unit.create', 'brand.create'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $this->user->syncPermissions(['product.create', 'product.update']);
    $this->user->syncRoles([]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-PCAD-28 · sem unit.create nem brand.create o Novo recebe os dois "+" desligados', function () {
    $this->withHeaders(pcaxVisitaInertia())->get('/products/create')->assertOk()
        ->assertJsonPath('props.podeCriarUnidade', false)
        ->assertJsonPath('props.podeCriarMarca', false);
});

it('UC-PCAD-28 · UC-PEDIT-28 · com as permissões o Novo e o Editar recebem os dois "+" ligados', function () {
    $this->user->givePermissionTo(['unit.create', 'brand.create']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $p = EstoqueFixture::singleProduct((int) $this->business->id);

    $this->withHeaders(pcaxVisitaInertia())->get('/products/create')->assertOk()
        ->assertJsonPath('props.podeCriarUnidade', true)
        ->assertJsonPath('props.podeCriarMarca', true);
    $this->withHeaders(pcaxVisitaInertia())->get('/products/' . $p->productId . '/edit')->assertOk()
        ->assertJsonPath('props.podeCriarUnidade', true)
        ->assertJsonPath('props.podeCriarMarca', true);
});

it('UC-PCAD-28 · o cadastro rápido grava a unidade e a marca na empresa e devolve o id que a tela escolhe', function () {
    $this->user->givePermissionTo(['unit.create', 'brand.create']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $json = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

    $u = $this->withHeaders($json)->postJson('/units', ['actual_name' => 'CR Caixa', 'short_name' => 'CRX', 'allow_decimal' => 0])->assertOk();
    expect($u->json('success'))->toBeTrue();
    expect(\App\Unit::find($u->json('data.id'))?->business_id)->toBe((int) $this->business->id);

    $b = $this->withHeaders($json)->postJson('/brands', ['name' => 'CR Marca', 'description' => ''])->assertOk();
    expect($b->json('success'))->toBeTrue();
    expect(\App\Brands::find($b->json('data.id'))?->business_id)->toBe((int) $this->business->id);
});
