<?php

declare(strict_types=1);

// Cobre UC-PCAD-25, UC-PCAD-26, UC-PCAD-27 (resources/js/Pages/Produto/Create.casos.md) e UC-PEDIT-25,
// UC-PEDIT-26, UC-PEDIT-27 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Subcategoria, sub-unidades e IMEI / nº de série no Novo e no Editar React (servidor).
 *
 * ÂNCORA (contrato, não implementação): protótipo do [W] (`produto-blade-forms.jsx`), pedido do [F]
 * em 2026-11-16 e paridade com `product/create.blade.php`: a subcategoria só aparece com
 * `enable_category` e `enable_sub_category`; as sub-unidades só com `enable_sub_units`, listando a
 * unidade principal e as que derivam dela no cadastro de unidades (`/products/get_sub_units`).
 *
 * O que cada caso trava:
 *   · com a empresa usando, a tela recebe as subcategorias por categoria e as sub-unidades por unidade,
 *     com o multiplicador do cadastro; sem usar, recebe as duas listas vazias;
 *   · nada de outra empresa entra nas listas [T0];
 *   · o Editar recebe o IMEI gravado; salvar com as sub-unidades e o IMEI grava os dois.
 *
 * ⛔ Tenant canônico de teste = 98 (ADR 0358). NUNCA biz=4.
 */
uses(DatabaseTransactions::class);

function pcscVisitaInertia(): array
{
    return [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ];
}

function pcscCategoria(int $biz, int $criador, string $nome, int $pai = 0): int
{
    $linha = ['name' => $nome, 'business_id' => $biz, 'parent_id' => $pai, 'created_by' => $criador, 'created_at' => now(), 'updated_at' => now()];
    if (Schema::hasColumn('categories', 'category_type')) {
        $linha['category_type'] = 'product';
    }

    return (int) DB::table('categories')->insertGetId($linha);
}

function pcscUnidade(int $biz, int $criador, string $nome, ?int $base = null, ?float $mult = null): int
{
    return (int) DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => $nome, 'short_name' => mb_substr($nome, 0, 3), 'allow_decimal' => 0,
        'created_by' => $criador, 'base_unit_id' => $base, 'base_unit_multiplier' => $mult,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('units', 'base_unit_id')) {
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
    $uid = (int) $this->user->id;
    $this->cat = pcscCategoria($biz, $uid, 'SC Banner');
    $this->sub = pcscCategoria($biz, $uid, 'SC Lona', $this->cat);
    $this->un = pcscUnidade($biz, $uid, 'SC Unidade');
    $this->cx = pcscUnidade($biz, $uid, 'SC Caixa', $this->un, 12.0);

    $this->actingAs($this->user);
    session([
        'user.business_id' => $biz, 'user.id' => $uid, 'business.id' => $biz,
        'business.enable_category' => 1, 'business.enable_sub_category' => 1, 'business.enable_sub_units' => 1,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.create', 'product.update']);
});

it('UC-PCAD-25 · UC-PCAD-26 · o Novo recebe as subcategorias por categoria e as sub-unidades com o multiplicador', function () {
    $r = $this->withHeaders(pcscVisitaInertia())->get('/products/create')->assertOk()->assertJsonPath('component', 'Produto/Create');

    expect($r->json('props.subcategoriasPorCategoria.' . $this->cat))->toBe([(string) $this->sub => 'SC Lona']);
    expect($r->json('props.subUnidadesPorUnidade.' . $this->un))->toBe([
        ['id' => $this->un, 'nome' => 'SC Unidade', 'multiplicador' => 1],
        ['id' => $this->cx, 'nome' => 'SC Caixa', 'multiplicador' => 12],
    ]);
    // A caixa não é unidade principal: não abre lista própria.
    expect($r->json('props.subUnidadesPorUnidade.' . $this->cx))->toBeNull();
});

it('UC-PCAD-25 · UC-PCAD-26 · empresa que não usa subcategoria nem sub-unidade recebe as listas vazias', function () {
    session(['business.enable_sub_category' => 0, 'business.enable_sub_units' => 0]);

    $r = $this->withHeaders(pcscVisitaInertia())->get('/products/create')->assertOk();
    expect($r->json('props.subcategoriasPorCategoria'))->toBe([]);
    expect($r->json('props.subUnidadesPorUnidade'))->toBe([]);
});

it('UC-PCAD-25 · UC-PCAD-26 · subcategoria e unidade de outra empresa não entram nas listas [T0]', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null || $outra === (int) $this->business->id) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    $alheia = pcscCategoria($outra, (int) $this->user->id, 'SC Alheia', $this->cat);
    $alheiaUn = pcscUnidade($outra, (int) $this->user->id, 'SC Alheia', $this->un, 6.0);

    $r = $this->withHeaders(pcscVisitaInertia())->get('/products/create')->assertOk();
    expect(array_keys((array) $r->json('props.subcategoriasPorCategoria.' . $this->cat)))->not->toContain((string) $alheia);
    expect(array_column((array) $r->json('props.subUnidadesPorUnidade.' . $this->un), 'id'))->not->toContain($alheiaUn);
});

it('UC-PEDIT-25 · UC-PEDIT-26 · UC-PEDIT-27 · o Editar recebe as listas e o IMEI gravado', function () {
    $p = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('products')->where('id', $p->productId)->update(['enable_sr_no' => 1]);

    $r = $this->withHeaders(pcscVisitaInertia())->get('/products/' . $p->productId . '/edit')->assertOk()->assertJsonPath('component', 'Produto/Edit');
    expect($r->json('props.product.enableSrNo'))->toBeTrue();
    expect($r->json('props.subcategoriasPorCategoria.' . $this->cat))->toBe([(string) $this->sub => 'SC Lona']);
    expect(array_column((array) $r->json('props.subUnidadesPorUnidade.' . $this->un), 'id'))->toBe([$this->un, $this->cx]);
});
