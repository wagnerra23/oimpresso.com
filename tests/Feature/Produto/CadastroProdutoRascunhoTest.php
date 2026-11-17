<?php

declare(strict_types=1);

// Cobre UC-PCAD-30 (resources/js/Pages/Produto/Create.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * A chave do rascunho do Novo produto (servidor).
 *
 * ÂNCORA (contrato, não implementação): protótipo do [W] (`produto-blade-forms.jsx`, rascunho do
 * cadastro no navegador), pedido do [F] em 2026-11-16 e ADR 0093 (a empresa vem da sessão, nunca do
 * cliente). A tela guarda o rascunho na chave que o servidor manda, com a empresa e o usuário da
 * sessão: outra empresa ou outro usuário no mesmo navegador não recupera o rascunho.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

it('UC-PCAD-30 · o Novo recebe a chave do rascunho com a empresa e o usuário da sessão [T0]', function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $business = $this->seededTenant();
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($user);
    session(['user.business_id' => (int) $business->id, 'user.id' => $user->id, 'business.id' => (int) $business->id]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $user->givePermissionTo('product.create');

    $this->withHeaders([
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])->get('/products/create')
        ->assertOk()
        ->assertJsonPath('component', 'Produto/Create')
        ->assertJsonPath('props.rascunho', $business->id . '.' . $user->id);
});
