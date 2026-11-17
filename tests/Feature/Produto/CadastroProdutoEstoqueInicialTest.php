<?php

declare(strict_types=1);

// Cobre UC-PCAD-21 (Create.casos.md) - G-2 rastreabilidade caso-teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * "Salvar e adicionar estoque inicial" pela tela React do Novo produto (UC-PCAD-21).
 *
 * O botão manda `submit_type=submit_n_add_opening_stock`, o mesmo do create.blade.php. O destino
 * (OpeningStockController@add) é Blade: num envio Inertia o store() devolve Inertia::location (409 com
 * o cabeçalho X-Inertia-Location), e o navegador abre a página inteira. O envio pelo Blade segue com o
 * redirect 302 de sempre. Exceção ao Non-Goal "NÃO modifica store()" do Create.charter.md, 2026-11-06.
 *
 * Tenant: o seeded do CI, nunca biz=4 (ADR 0358).
 */
uses(DatabaseTransactions::class);

function pcadeiEnvio(int $bizId): array
{
    return [
        'name' => 'Produto UC-PCAD-21 ' . uniqid(),
        'sku' => '',
        'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId),
        'tax_type' => 'exclusive',
        'enable_stock' => 1,
        'submit_type' => 'submit_n_add_opening_stock',
    ];
}

function pcadeiProduto(string $nome, int $bizId): ?object
{
    return DB::table('products')->where('name', $nome)->where('business_id', $bizId)->first();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->business = $this->seededTenant();

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.create']);
});

it('UC-PCAD-21 · envio pela tela React salva e manda o navegador abrir o estoque inicial (Inertia::location)', function () {
    $bizId = (int) $this->business->id;
    $envio = pcadeiEnvio($bizId);

    $resposta = $this->post('/products', $envio, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ]);

    $produto = pcadeiProduto($envio['name'], $bizId);
    expect($produto)->not->toBeNull('PRÉ-CONDIÇÃO: o produto não foi salvo, então o destino não foi exercido.');
    expect($resposta->status())->toBe(409);
    expect((string) $resposta->headers->get('X-Inertia-Location'))->toEndWith('/opening-stock/add/' . $produto->id);
});

it('UC-PCAD-21 · envio pelo Blade segue com o redirect 302 para o estoque inicial, como sempre', function () {
    $bizId = (int) $this->business->id;
    $envio = pcadeiEnvio($bizId);

    $resposta = $this->post('/products', $envio);

    $produto = pcadeiProduto($envio['name'], $bizId);
    expect($produto)->not->toBeNull('PRÉ-CONDIÇÃO: o produto não foi salvo, então o destino não foi exercido.');
    $resposta->assertRedirect('/opening-stock/add/' . $produto->id);
});
