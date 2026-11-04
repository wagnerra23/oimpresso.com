<?php

declare(strict_types=1);
// Cobre UC-PIDX-37 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * O saldo de cada cor e tamanho na própria lista (D7). Mexe em ESTOQUE (só leitura), então a
 * conta é provada por dois caminhos: (1) o saldo de cada variação é o que foi lançado em cada
 * local, somado à mão no teste; (2) a soma das variações é o `estoque` que a MESMA linha mostra.
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
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Requested-With' => 'XMLHttpRequest',
    ];
});

function psvEntrar($teste, array $permissoes = ['product.view', 'access_all_locations']): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function psvLinha($teste, int $produtoId): ?array
{
    $r = $teste->get('/products?per_page=-1', $teste->inertia + ['X-Inertia-Partial-Data' => 'lista']);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');

    foreach ($r->json('props.lista.data') as $linha) {
        if ($linha['id'] === $produtoId) {
            return $linha;
        }
    }

    return null;
}

it('UC-PIDX-37 · cada variação traz o próprio saldo, somado entre os locais, e a soma é o saldo da linha', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::variableProduct($biz, 3);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    // Saldos conhecidos, por variação e local. A 3ª variação fica sem linha de estoque nenhuma.
    EstoqueFixture::setStock($p, 0, $matriz, 7);
    EstoqueFixture::setStock($p, 0, $filial, 2.5);
    EstoqueFixture::setStock($p, 1, $matriz, 4);

    psvEntrar($this);
    $linha = psvLinha($this, $p->productId);
    expect($linha)->not->toBeNull();

    $skus = DB::table('variations')->whereIn('id', array_column($p->variations, 'variation_id'))->orderBy('id')->pluck('sub_sku')->all();
    expect(array_column($linha['saldo_variacoes'], 'sku'))->toBe($skus);

    // Caminho 1: cada variação, contra a soma feita à mão.
    expect(array_column($linha['saldo_variacoes'], 'saldo'))->toEqual([9.5, 4.0, 0.0]);
    // Caminho 2: a soma das variações é o saldo que a mesma linha mostra na coluna Estoque.
    expect(array_sum(array_column($linha['saldo_variacoes'], 'saldo')))->toEqual($linha['estoque']);
    expect($linha['estoque'])->toEqual(13.5);
    // Nome igual ao do painel (aba Variações): grupo - variação.
    expect($linha['saldo_variacoes'][0]['nome'])->toBe('Var 1 - Var 1');
});

it('UC-PIDX-37 · só vem em produto variável que controla estoque', function () {
    $biz = $this->business->id;
    $simples = EstoqueFixture::singleProduct($biz);
    $semControle = EstoqueFixture::variableProduct($biz, 2);
    DB::table('products')->where('id', $semControle->productId)->update(['enable_stock' => 0]);

    psvEntrar($this);
    $linhaSimples = psvLinha($this, $simples->productId);
    $linhaSemControle = psvLinha($this, $semControle->productId);

    // Controle positivo: as duas linhas vieram.
    expect($linhaSimples)->not->toBeNull();
    expect($linhaSemControle)->not->toBeNull();
    expect($linhaSimples)->not->toHaveKey('saldo_variacoes');
    expect($linhaSemControle)->not->toHaveKey('saldo_variacoes');
});

it('UC-PIDX-37 · variação excluída fica fora, como na coluna Estoque', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::variableProduct($biz, 2);
    $matriz = EstoqueFixture::locationId($biz);
    EstoqueFixture::setStock($p, 0, $matriz, 3);
    EstoqueFixture::setStock($p, 1, $matriz, 8);
    DB::table('variations')->where('id', $p->variations[1]['variation_id'])->update(['deleted_at' => now()]);

    psvEntrar($this);
    $linha = psvLinha($this, $p->productId);

    expect(array_column($linha['saldo_variacoes'], 'saldo'))->toEqual([3.0]);
    expect($linha['estoque'])->toEqual(3.0);
});

it('UC-PIDX-37 · quem só vê um local recebe o saldo daquele local, e a soma segue igual à linha', function () {
    $biz = $this->business->id;
    $p = EstoqueFixture::variableProduct($biz, 2);
    $matriz = EstoqueFixture::locationId($biz);
    $filial = EstoqueFixture::locationId($biz, 'filial');
    EstoqueFixture::setStock($p, 0, $matriz, 6);
    EstoqueFixture::setStock($p, 0, $filial, 10);
    EstoqueFixture::setStock($p, 1, $filial, 5);
    DB::table('product_locations')->insertOrIgnore([
        ['product_id' => $p->productId, 'location_id' => $matriz],
        ['product_id' => $p->productId, 'location_id' => $filial],
    ]);

    // `permitted_locations()` lê a permissão DIRETA do usuário (não a do papel), como no
    // ProdutoListaCadastroRapidoTest.
    psvEntrar($this, ['product.view']);
    \Spatie\Permission\Models\Permission::findOrCreate('location.' . $matriz, 'web');
    $this->user->givePermissionTo('location.' . $matriz);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = \App\User::findOrFail($this->user->id);
    $this->actingAs($this->user);
    expect($this->user->permitted_locations())->toBe([$matriz]);
    $linha = psvLinha($this, $p->productId);
    expect($linha)->not->toBeNull();

    expect(array_column($linha['saldo_variacoes'], 'saldo'))->toEqual([6.0, 0.0]);
    expect($linha['estoque'])->toEqual(6.0);
});
