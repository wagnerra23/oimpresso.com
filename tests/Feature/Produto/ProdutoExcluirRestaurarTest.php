<?php

declare(strict_types=1);
// Cobre UC-PIDX-45 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Excluir sem perder e restaurar (D13 · AR-PROD-022, [W] 2026-07-13): com `product.delete`, qualquer produto
 * pode ser excluído; o cadastro vira inativo e fica marcado em `deleted_at`, some da lista e aparece em
 * "Situação: Excluídos". Nada é apagado: variações, estoque e histórico ficam. "Restaurar" devolve a Ativo.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
    $this->ajax = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
});

function perEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function perLista($teste, string $query): array
{
    $r = $teste->get('/products' . $query, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'lista',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $r->assertOk();

    return collect($r->json('props.lista.data'))->keyBy('id')->all();
}

/** Produto simples com 18 no estoque de um local. */
function perProduto(int $biz): object
{
    $p = EstoqueFixture::singleProduct($biz);
    EstoqueFixture::setStock($p, 0, EstoqueFixture::locationId($biz), 18);

    return $p;
}

function perEstoque(int $productId): float
{
    return (float) DB::table('variation_location_details')->where('product_id', $productId)->sum('qty_available');
}

it('UC-PIDX-45 · excluir guarda tudo: some da lista, aparece em Excluídos, com estoque e variações', function () {
    $biz = $this->business->id;
    $p = perProduto($biz);
    $variacoes = DB::table('variations')->where('product_id', $p->productId)->count();
    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);

    // Controle positivo: antes, ele está na lista.
    expect(perLista($this, '?per_page=-1'))->toHaveKey($p->productId);

    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('products')->where('id', $p->productId)->first();
    expect($linha)->not->toBeNull();
    expect((int) $linha->is_inactive)->toBe(1);
    expect($linha->deleted_at)->not->toBeNull();
    expect(DB::table('variations')->where('product_id', $p->productId)->count())->toBe($variacoes);
    expect(abs(perEstoque($p->productId) - 18))->toBeLessThan(0.005);

    expect(perLista($this, '?per_page=-1'))->not->toHaveKey($p->productId);
    expect(perLista($this, '?active_state=inactive&per_page=-1'))->not->toHaveKey($p->productId);
    $excluidos = perLista($this, '?active_state=excluido&per_page=-1');
    expect($excluidos)->toHaveKey($p->productId);
    expect($excluidos[$p->productId]['excluido'])->toBeTrue();
});

it('UC-PIDX-45 · produto com compra também pode ser excluído (qualquer produto, com permissão)', function () {
    $biz = $this->business->id;
    $p = perProduto($biz);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => now(), 'final_total' => 10, 'total_before_tax' => 10,
        'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0, 'ref_no' => 'PER-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'], 'quantity' => 1,
        'purchase_price' => 10, 'purchase_price_inc_tax' => 10, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);

    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertOk()->assertJson(['success' => true]);

    expect(DB::table('products')->where('id', $p->productId)->value('deleted_at'))->not->toBeNull();
    expect(DB::table('purchase_lines')->where('transaction_id', $compra)->count())->toBe(1);
});

it('UC-PIDX-45 · restaurar devolve a Ativo, com o mesmo estoque, e Ativar não traz excluído de volta', function () {
    $biz = $this->business->id;
    $p = perProduto($biz);
    perEntrar($this, ['product.view', 'product.update', 'product.delete', 'access_all_locations']);
    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertOk();

    // "Ativar" não mexe no excluído: o caminho é Restaurar.
    $this->withHeaders($this->ajax)->get('/products/activate/' . $p->productId)->assertOk();
    expect((int) DB::table('products')->where('id', $p->productId)->value('is_inactive'))->toBe(1);

    $this->withHeaders($this->ajax)->post('/products/restaurar/' . $p->productId)->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('products')->where('id', $p->productId)->first();
    expect((int) $linha->is_inactive)->toBe(0);
    expect($linha->deleted_at)->toBeNull();
    expect(abs(perEstoque($p->productId) - 18))->toBeLessThan(0.005);
    expect(perLista($this, '?per_page=-1'))->toHaveKey($p->productId);

    // Restaurar o que não está excluído não acha nada.
    $this->withHeaders($this->ajax)->post('/products/restaurar/' . $p->productId)->assertNotFound();
});

it('UC-PIDX-45 · Excluir selecionados exclui os da empresa e não toca o de outra', function () {
    $biz = $this->business->id;
    $a = perProduto($biz);
    $b = perProduto($biz);
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $deFora = EstoqueFixture::singleProduct((int) $outra);
    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);

    $this->post('/products/mass-delete', ['selected_rows' => implode(',', [$a->productId, $b->productId, $deFora->productId])])->assertRedirect();

    expect(DB::table('products')->where('id', $a->productId)->value('deleted_at'))->not->toBeNull();
    expect(DB::table('products')->where('id', $b->productId)->value('deleted_at'))->not->toBeNull();
    expect(DB::table('products')->where('id', $deFora->productId)->value('deleted_at'))->toBeNull();
    expect((int) DB::table('products')->where('id', $deFora->productId)->value('is_inactive'))->toBe(0);
});

it('UC-PIDX-45 · excluir e restaurar pedem product.delete; produto de outra empresa não é achado', function () {
    $biz = $this->business->id;
    $p = perProduto($biz);
    $outra = EstoqueFixture::secondBusinessId();
    $deFora = EstoqueFixture::singleProduct((int) $outra);

    perEntrar($this, ['product.view', 'access_all_locations']);
    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertForbidden();
    $this->withHeaders($this->ajax)->post('/products/restaurar/' . $p->productId)->assertForbidden();
    expect(DB::table('products')->where('id', $p->productId)->value('deleted_at'))->toBeNull();

    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);
    $this->withHeaders($this->ajax)->delete('/products/' . $deFora->productId)->assertOk()->assertJson(['success' => false]);
    expect(DB::table('products')->where('id', $deFora->productId)->value('deleted_at'))->toBeNull();
    DB::table('products')->where('id', $deFora->productId)->update(['deleted_at' => now()]);
    $this->withHeaders($this->ajax)->post('/products/restaurar/' . $deFora->productId)->assertNotFound();
});

it('UC-PIDX-45 · o painel do produto sabe que ele está excluído (e deixa de saber ao restaurar)', function () {
    // Decisão [F] 2026-11-11: o painel tira "Editar produto" e "Preços por grupo" do excluído. Para isso o
    // detalhe (`?ver=`) leva `excluido`; o controle negativo é o mesmo produto antes de excluir e depois de restaurar.
    $biz = $this->business->id;
    $p = perProduto($biz);
    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);
    $detalhe = function () use ($p) {
        $r = $this->get('/products?ver=' . $p->productId, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Produto/Index',
            'X-Inertia-Partial-Data' => 'detalhe',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $r->assertOk();

        return $r->json('props.detalhe');
    };

    expect($detalhe()['excluido'])->toBeFalse();

    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertOk();
    expect($detalhe()['excluido'])->toBeTrue();

    $this->withHeaders($this->ajax)->post('/products/restaurar/' . $p->productId)->assertOk();
    expect($detalhe()['excluido'])->toBeFalse();
});

it('UC-PIDX-45 · o indicador "Total" deixa de contar o excluído', function () {
    $biz = $this->business->id;
    $p = perProduto($biz);
    perEntrar($this, ['product.view', 'product.delete', 'access_all_locations']);
    $kpis = function () {
        $r = $this->get('/products', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Produto/Index',
            'X-Inertia-Partial-Data' => 'kpis',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $r->assertOk();

        return $r->json('props.kpis');
    };
    $antes = $kpis();

    $this->withHeaders($this->ajax)->delete('/products/' . $p->productId)->assertOk();

    $depois = $kpis();
    expect($depois['total'])->toBe($antes['total'] - 1);
    expect($depois['inativos'])->toBe($antes['inativos']);
});
