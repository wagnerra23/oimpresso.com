<?php

declare(strict_types=1);
// Cobre UC-PIDX-43 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * "Informar NCM" na barra de selecionados (`POST /products/bulk-update-ncm`), pedido da PraMoto em
 * 2026-10-27. Grava o mesmo NCM nos produtos selecionados da empresa do usuário, com a regra de NCM válido
 * da emissão (8 dígitos, não 00000000). Isolamento Tier 0 (ADR 0093): produto de outra empresa no meio da
 * lista não muda.
 *
 * Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    $this->business = $this->seededTenant();
});

function pnlEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pnlProduto(int $biz, string $ncm = '0'): int
{
    $p = EstoqueFixture::singleProduct($biz);
    DB::table('products')->where('id', $p->productId)->update(['ncm' => $ncm]);

    return $p->productId;
}

function pnlNcm(int $id): string
{
    return (string) DB::table('products')->where('id', $id)->value('ncm');
}

function pnlEnviar($teste, array $ids, string $ncm)
{
    return $teste->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->postJson('/products/bulk-update-ncm', ['products' => implode(',', $ids), 'ncm' => $ncm]);
}

it('UC-PIDX-43 · grava o NCM nos selecionados, aceita com pontos, e não toca o que não foi escolhido', function () {
    $biz = $this->business->id;
    $a = pnlProduto($biz);
    $b = pnlProduto($biz, '1234');
    $c = pnlProduto($biz, '00000000');
    $fora = pnlProduto($biz, '49111090');

    pnlEntrar($this, ['product.view', 'product.update', 'access_all_locations']);
    pnlEnviar($this, [$a, $b, $c], '3921.90.90')->assertOk()->assertJson(['success' => 1]);

    expect([pnlNcm($a), pnlNcm($b), pnlNcm($c)])->toBe(['39219090', '39219090', '39219090']);
    expect(pnlNcm($fora))->toBe('49111090');
});

it('UC-PIDX-43 · recusa NCM com 7 dígitos, 00000000 e lista vazia, sem gravar nada', function () {
    $biz = $this->business->id;
    $a = pnlProduto($biz);

    pnlEntrar($this, ['product.view', 'product.update', 'access_all_locations']);
    pnlEnviar($this, [$a], '3921909')->assertStatus(422)->assertJson(['success' => 0]);
    pnlEnviar($this, [$a], '0000.00.00')->assertStatus(422)->assertJson(['success' => 0]);
    pnlEnviar($this, [], '39219090')->assertStatus(422)->assertJson(['success' => 0]);

    expect(pnlNcm($a))->toBe('0');
});

it('UC-PIDX-43 · produto de outra empresa no meio da lista não muda', function () {
    $biz = $this->business->id;
    $meu = pnlProduto($biz);
    $outra = EstoqueFixture::secondBusinessId();
    expect($outra)->not->toBeNull();
    $deFora = pnlProduto((int) $outra);

    pnlEntrar($this, ['product.view', 'product.update', 'access_all_locations']);
    pnlEnviar($this, [$meu, $deFora], '39219090')->assertOk()->assertJson(['success' => 1, 'msg' => '1 produto(s) com o NCM 39219090.']);

    expect(pnlNcm($meu))->toBe('39219090');
    expect(pnlNcm($deFora))->toBe('0');
});

it('UC-PIDX-43 · sem permissão de editar produto é 403 e nada muda', function () {
    $biz = $this->business->id;
    $a = pnlProduto($biz);

    pnlEntrar($this, ['product.view', 'access_all_locations']);
    pnlEnviar($this, [$a], '39219090')->assertForbidden();

    expect(pnlNcm($a))->toBe('0');
});

it('UC-PIDX-43 · os alterados saem do filtro "Falta NCM"', function () {
    $biz = $this->business->id;
    $a = pnlProduto($biz);
    $b = pnlProduto($biz);

    pnlEntrar($this, ['product.view', 'product.update', 'access_all_locations']);
    $lista = function () {
        $r = $this->get('/products?falta_ncm=1&per_page=-1', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Produto/Index',
            'X-Inertia-Partial-Data' => 'lista',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $r->assertOk();

        return array_column($r->json('props.lista.data'), 'id');
    };

    // Controle positivo: antes, os dois estão no filtro.
    expect($lista())->toContain($a, $b);
    pnlEnviar($this, [$a], '39219090')->assertOk();
    $depois = $lista();
    expect($depois)->not->toContain($a);
    expect($depois)->toContain($b);
});
