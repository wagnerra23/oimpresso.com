<?php

declare(strict_types=1);
// Cobre UC-PIDX-13 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Ordenação da lista de produtos (`Produto/Index`, prop `lista`, `?sort=&dir=`) — o lado do SERVIDOR
 * do clique no cabeçalho da coluna (protótipo `produto-blade.jsx`, `ListaProdutos`, `sortable`).
 * O lado da tela (o botão no cabeçalho e o pedido que ele manda) é o
 * `tests/js/produto-lista-ordenacao.test.tsx`.
 *
 * Três produtos com nome, estoque e custo em ordens DIFERENTES entre si, para cada ordenação ter
 * uma resposta que nenhuma das outras daria:
 *   nome     Alfa · Bravo · Charlie
 *   estoque     5 ·     9 ·       1
 *   custo      30 ·    10 ·      20
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->inertia = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function ploEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Cria Alfa, Bravo e Charlie e devolve o termo de busca que só acha os três. */
function ploTresProdutos($teste): string
{
    $marca = 'PLO' . strtoupper(uniqid());
    $local = EstoqueFixture::locationId($teste->business->id, '-PLO-' . uniqid());
    foreach ([['Alfa', 5, 30], ['Bravo', 9, 10], ['Charlie', 1, 20]] as [$nome, $estoque, $custo]) {
        $p = EstoqueFixture::singleProduct($teste->business->id);
        DB::table('products')->where('id', $p->productId)->update(['name' => $marca . ' ' . $nome]);
        DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update(['default_purchase_price' => $custo, 'dpp_inc_tax' => $custo]);
        DB::table('product_locations')->insert(['product_id' => $p->productId, 'location_id' => $local]);
        EstoqueFixture::setStock($p, 0, $local, $estoque);
    }

    return $marca;
}

/** As linhas da prop `lista` para a busca e a ordem pedidas (a prop é deferida: partial reload). */
function ploLinhas($teste, string $busca, array $ordem = []): array
{
    $r = $teste->get('/products?' . http_build_query(array_merge(['q' => $busca], $ordem)), $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'lista',
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect($r->json('props'))->toHaveKey('lista');

    return $r->json('props.lista.data');
}

/** Só o último nome de cada linha ("Alfa", "Bravo", "Charlie"), na ordem em que vieram. */
function ploNomes(array $linhas): array
{
    return array_map(fn (array $l): string => (string) last(explode(' ', (string) $l['nome'])), $linhas);
}

it('UC-PIDX-13 · ordena por estoque nas duas direções', function () {
    ploEntrar($this, ['product.view', 'access_all_locations']);
    $busca = ploTresProdutos($this);

    $desc = ploLinhas($this, $busca, ['sort' => 'estoque', 'dir' => 'desc']);
    // Anti-vácuo: a busca achou exatamente os três do cenário.
    expect($desc)->toHaveCount(3);
    expect(ploNomes($desc))->toBe(['Bravo', 'Alfa', 'Charlie']);
    expect(ploNomes(ploLinhas($this, $busca, ['sort' => 'estoque', 'dir' => 'asc'])))->toBe(['Charlie', 'Alfa', 'Bravo']);
});

it('UC-PIDX-13 · sem pedido de ordem vem por nome; por nome também inverte; chave desconhecida cai no nome', function () {
    ploEntrar($this, ['product.view', 'access_all_locations']);
    $busca = ploTresProdutos($this);

    expect(ploNomes(ploLinhas($this, $busca)))->toBe(['Alfa', 'Bravo', 'Charlie']);
    expect(ploNomes(ploLinhas($this, $busca, ['sort' => 'produto', 'dir' => 'desc'])))->toBe(['Charlie', 'Bravo', 'Alfa']);
    expect(ploNomes(ploLinhas($this, $busca, ['sort' => 'coluna_que_nao_existe', 'dir' => 'asc'])))->toBe(['Alfa', 'Bravo', 'Charlie']);
});

it('UC-PIDX-13 · ordena por preço de compra só para quem pode ver o preço de compra', function () {
    ploEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $busca = ploTresProdutos($this);

    // Com a permissão: custo decrescente (30 · 20 · 10) — ordem que nem o nome nem o estoque dariam.
    $com = ploLinhas($this, $busca, ['sort' => 'compra', 'dir' => 'desc']);
    expect($com)->toHaveCount(3);
    expect(ploNomes($com))->toBe(['Alfa', 'Charlie', 'Bravo']);
    expect($com[0])->toHaveKey('compra_max');

    // Sem a permissão o mesmo pedido cai na ordem por nome: a ordem das linhas não revela o custo.
    // "desc" continua valendo, então é o nome decrescente — o que importa é NÃO ser a ordem do custo.
    ploEntrar($this, ['product.view', 'access_all_locations']);
    $sem = ploLinhas($this, $busca, ['sort' => 'compra', 'dir' => 'desc']);
    expect($sem)->toHaveCount(3);
    expect(ploNomes($sem))->toBe(['Charlie', 'Bravo', 'Alfa']);
    expect($sem[0])->not->toHaveKey('compra_max');
});
