<?php

declare(strict_types=1);

// Cobre UC-PCAD-31 (resources/js/Pages/Produto/Create.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Nome e unidade obrigatórios no salvar do Novo produto (UC-PCAD-31).
 *
 * ÂNCORA (contrato, não implementação): CU-PROD-01 do SDD do cadastro de produto (nome e unidade
 * são os campos mínimos) + paridade com o cadastro rápido (`cadastroRapido`, que já exige os dois)
 * e com o Blade (`create.blade.php` marca os dois como obrigatórios).
 *
 * Por que existe: em 2026-11-16, testando a tela React no navegador, um produto foi salvo sem
 * unidade. A lista de produtos faz `join units`, então ele sumiu dela.
 *
 * O que cada caso trava:
 *   · sem unidade (vazio ou ausente) é recusado e nenhum produto nasce;
 *   · sem nome (vazio ou só espaços) é recusado e nenhum produto nasce;
 *   · com nome e unidade o produto nasce, com a unidade enviada.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

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
    $this->user->givePermissionTo(['product.create']);
});

function pcobrigBase(int $bizId, string $nome): array
{
    return [
        'name' => $nome, 'sku' => '', 'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId), 'tax_type' => 'exclusive', 'enable_stock' => 1,
    ];
}

it('UC-PCAD-31 · sem unidade é recusado e nenhum produto nasce', function (array $semUnidade) {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-31 sem unidade ' . uniqid();
    $pedido = array_merge(pcobrigBase($bizId, $nome), $semUnidade);
    if (! array_key_exists('unit_id', $semUnidade)) {
        unset($pedido['unit_id']);
    }

    $this->post('/products', $pedido)->assertSessionHasErrors(['unit_id' => 'Escolha a unidade.']);

    expect(DB::table('products')->where('business_id', $bizId)->where('name', $nome)->exists())->toBeFalse();
})->with([
    'unidade vazia (tela React)' => [['unit_id' => '']],
    'sem o campo' => [[]],
]);

it('UC-PCAD-31 · sem nome é recusado e nenhum produto nasce', function (string $nome) {
    $bizId = (int) $this->business->id;
    $antes = DB::table('products')->where('business_id', $bizId)->count();

    $this->post('/products', pcobrigBase($bizId, $nome))->assertSessionHasErrors(['name' => 'Informe o nome do produto.']);

    expect(DB::table('products')->where('business_id', $bizId)->count())->toBe($antes);
})->with([
    'vazio' => [''],
    'só espaços' => ['   '],
]);

it('UC-PCAD-31 · com nome e unidade o produto nasce com a unidade enviada', function () {
    $bizId = (int) $this->business->id;
    $nome = 'Produto UC-PCAD-31 completo ' . uniqid();

    $this->post('/products', pcobrigBase($bizId, $nome))->assertSessionHasNoErrors();

    $produto = DB::table('products')->where('business_id', $bizId)->where('name', $nome)->first();
    expect($produto)->not->toBeNull();
    expect((int) $produto->unit_id)->toBe(EstoqueFixture::unitId($bizId));
});
