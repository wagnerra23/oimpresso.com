<?php

declare(strict_types=1);
// Cobre UC-PIDX-31 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Consulta de produto (`/products` → `Produto/Index`): a natureza fiscal do produto.
 *
 *  UC-PIDX-31 · UC-FLX-31 — a natureza decidida pelo contador (`nfe_naturezas_produto`, US-NFE-068):
 *    selo na linha, filtro e a natureza na aba Fiscal. Sem decisão = mercadoria.
 *
 * Os valores esperados vêm das linhas gravadas aqui, nunca da própria tela. Tenant semeado do CI
 * (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
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

function pnfEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pnfProp($teste, string $url, string $prop): mixed
{
    $r = $teste->get($url, $teste->inertia + [
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => $prop,
    ]);
    $r->assertOk();
    expect($r->json('component'))->toBe('Produto/Index');
    expect($r->json('props'))->toHaveKey($prop);

    return $r->json('props.' . $prop);
}

/** As linhas da lista com a busca pelo prefixo de SKU da fixture e os filtros pedidos. */
function pnfLinhas($teste, array $query = []): array
{
    return pnfpSemPaginar(pnfProp($teste, '/products?' . http_build_query($query + ['q' => 'ESTFIX-', 'per_page' => -1]), 'lista'));
}

function pnfpSemPaginar(array $lista): array
{
    return collect($lista['data'])->keyBy('id')->all();
}

function pnfLinha($teste, object $p): array
{
    $sku = (string) DB::table('products')->where('id', $p->productId)->value('sku');
    $linhas = collect(pnfProp($teste, '/products?' . http_build_query(['q' => $sku]), 'lista')['data'])->where('id', $p->productId)->values();
    expect($linhas)->toHaveCount(1);

    return $linhas[0];
}

function pnfNatureza(int $businessId, int $productId, string $natureza, ?string $codigo = null): void
{
    DB::table('nfe_naturezas_produto')->insert([
        'business_id' => $businessId, 'product_id' => $productId, 'natureza' => $natureza, 'codigo_servico' => $codigo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

// ─── UC-PIDX-31 · natureza fiscal ──────────────────────────────────────────

it('UC-PIDX-31 · a linha traz a natureza decidida; sem decisão é mercadoria; o painel mostra o item da LC 116', function () {
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Módulo Fiscal sem migration no schema deste ambiente.');
    }
    pnfEntrar($this, ['product.view', 'access_all_locations']);
    $merc = EstoqueFixture::singleProduct($this->business->id);
    $serv = EstoqueFixture::singleProduct($this->business->id);
    $pend = EstoqueFixture::singleProduct($this->business->id);
    pnfNatureza($this->business->id, $serv->productId, 'servico', '24.01');
    pnfNatureza($this->business->id, $pend->productId, 'pendente');

    expect([pnfLinha($this, $merc)['natureza_fiscal'], pnfLinha($this, $serv)['natureza_fiscal'], pnfLinha($this, $pend)['natureza_fiscal']])
        ->toBe(['mercadoria', 'servico', 'pendente']);
    expect(pnfProp($this, '/products?ver=' . $serv->productId, 'detalhe')['fiscal']['natureza'])
        ->toEqual(['natureza' => 'servico', 'codigo_servico' => '24.01']);
    expect(pnfProp($this, '/products?ver=' . $merc->productId, 'detalhe')['fiscal']['natureza'])
        ->toEqual(['natureza' => 'mercadoria', 'codigo_servico' => null]);
});

it('UC-PIDX-31 · o filtro "Natureza fiscal" é resolvido no servidor, nas três opções', function () {
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Módulo Fiscal sem migration no schema deste ambiente.');
    }
    pnfEntrar($this, ['product.view', 'access_all_locations']);
    $merc = EstoqueFixture::singleProduct($this->business->id);
    $serv = EstoqueFixture::singleProduct($this->business->id);
    $pend = EstoqueFixture::singleProduct($this->business->id);
    pnfNatureza($this->business->id, $serv->productId, 'servico', '24.01');
    pnfNatureza($this->business->id, $pend->productId, 'pendente');
    $nossos = [$merc->productId, $serv->productId, $pend->productId];
    $ids = fn (array $q) => array_values(array_intersect($nossos, array_keys(pnfLinhas($this, $q))));

    // Controle: sem o filtro, os três aparecem.
    expect($ids([]))->toBe($nossos);
    expect($ids(['natureza_fiscal' => 'pendente']))->toBe([$pend->productId]);
    expect($ids(['natureza_fiscal' => 'servico']))->toBe([$serv->productId]);
    expect($ids(['natureza_fiscal' => 'mercadoria']))->toBe([$merc->productId]);
});

it('UC-PIDX-31 · decisão gravada com outra empresa não vale para o produto (Tier 0)', function () {
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Módulo Fiscal sem migration no schema deste ambiente.');
    }
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    pnfEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    pnfNatureza($outra, $p->productId, 'pendente');

    expect(pnfLinha($this, $p)['natureza_fiscal'])->toBe('mercadoria');
    $ids = array_keys(pnfLinhas($this, ['natureza_fiscal' => 'pendente']));
    expect($ids)->not->toContain($p->productId);
});
