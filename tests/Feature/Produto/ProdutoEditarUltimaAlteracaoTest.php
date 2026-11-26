<?php

declare(strict_types=1);

// Cobre UC-PEDIT-36 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * "Última alteração" no Editar React (UC-PEDIT-36, AR-PROD-004 — paridade com o "Alterar produto"
 * do Delphi). O edit() manda `product.ultimaAlteracao` com o updated_at do produto em dd/mm/aaaa hh:mm,
 * sem o +3h do format_date. É só leitura: a tela não tem campo para mudá-la.
 *
 * Tenant: o seeded do CI (98), nunca biz=4 (ADR 0358).
 */
uses(DatabaseTransactions::class);

function peditTrintaSeisInertia(): array
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

it('UC-PEDIT-36 · o Editar React recebe a última alteração do produto em dd/mm/aaaa hh:mm', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);
    DB::table('products')->where('id', $p->productId)->where('business_id', $this->business->id)
        ->update(['updated_at' => '2026-11-20 14:35:00']);

    $resposta = $this->get("/products/{$p->productId}/edit", peditTrintaSeisInertia());

    $resposta->assertOk();
    expect($resposta->json('component'))->toBe('Produto/Edit');
    expect($resposta->json('props.product.ultimaAlteracao'))->toBe('20/11/2026 14:35');
});

it('UC-PEDIT-36 · produto sem updated_at manda null, e a tela não mostra a linha', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);
    DB::table('products')->where('id', $p->productId)->where('business_id', $this->business->id)
        ->update(['updated_at' => null]);

    $resposta = $this->get("/products/{$p->productId}/edit", peditTrintaSeisInertia());

    $resposta->assertOk();
    expect($resposta->json('component'))->toBe('Produto/Edit');
    expect($resposta->json('props.product'))->toHaveKey('ultimaAlteracao');
    expect($resposta->json('props.product.ultimaAlteracao'))->toBeNull();
});

it('UC-PEDIT-36 · salvar pelo Editar atualiza a data que a tela mostra', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);
    DB::table('products')->where('id', $p->productId)->where('business_id', $this->business->id)
        ->update(['updated_at' => '2020-01-01 00:00:00']);

    Product::findOrFail($p->productId)->update(['name' => 'Produto UC-PEDIT-36']);

    $resposta = $this->get("/products/{$p->productId}/edit", peditTrintaSeisInertia());

    $resposta->assertOk();
    expect($resposta->json('props.product.ultimaAlteracao'))->not->toBe('01/01/2020 00:00');
    expect($resposta->json('props.product.ultimaAlteracao'))->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});
