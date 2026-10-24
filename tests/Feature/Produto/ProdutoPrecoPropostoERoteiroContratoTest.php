<?php

declare(strict_types=1);
// Cobre UC-PIDX-28 e UC-PIDX-29 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Consulta de produto (`/products` → `Produto/Index`) mostra dois fluxos do Wagner:
 *
 *  UC-PIDX-28 · UC-FLX-17 — a compra recebida propôs preço novo, que aguarda aprovação. A lista e o
 *    painel avisam; aprovar e recusar ficam no detalhe da compra (US-COM-028). Fonte da proposta:
 *    `compras_propostas_preco` (US-COM-026). Os valores esperados vêm da linha gravada aqui, nunca
 *    da própria tela.
 *  UC-PIDX-29 · UC-FLX-07 · UC-FLX-08 — o painel mostra o roteiro ligado ao produto
 *    (`pcp_produto_roteiro`, o mesmo vínculo que a OS usa), com o posto e o setor de cada etapa.
 *
 * Âncoras: Compras/SPEC US-COM-026/028, Producao/SPEC US-PCP-026/030, charter do Produto/Index
 * (a consulta não altera preço). Tenant semeado do CI (ADR 0358). Nunca biz=4. Pest só no CI / CT 100.
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

function pprEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

/** Uma prop deferida da Page, pedida por partial reload. */
function pprProp($teste, string $url, string $prop): mixed
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

/** A linha do produto na lista, achada pelo SKU. */
function pprLinha($teste, object $p): array
{
    $sku = (string) DB::table('products')->where('id', $p->productId)->value('sku');
    $linhas = collect(pprProp($teste, '/products?' . http_build_query(['q' => $sku]), 'lista')['data'])->where('id', $p->productId)->values();
    expect($linhas)->toHaveCount(1);

    return $linhas[0];
}

/** Compra recebida + proposta de preço gravadas como o recebimento grava (US-COM-026). */
function pprProposta(int $businessId, int $variationId, float $antes, float $proposto, string $status = 'pendente', ?int $propostaBusinessId = null): array
{
    $ref = 'PPR-' . uniqid();
    $compraId = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => '2026-10-12 09:00:00', 'ref_no' => $ref, 'final_total' => 100, 'total_before_tax' => 100,
        'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => EstoqueFixture::userId($businessId),
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $id = DB::table('compras_propostas_preco')->insertGetId([
        'business_id' => $propostaBusinessId ?? $businessId, 'variation_id' => $variationId, 'transaction_id' => $compraId,
        'custo_antes' => 10, 'custo_depois' => 11, 'preco_antes' => $antes, 'preco_proposto' => $proposto,
        'status' => $status, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['id' => $id, 'compra_id' => $compraId, 'ref' => $ref];
}

function pprProdutoComPreco(int $businessId, float $venda): object
{
    $p = EstoqueFixture::singleProduct($businessId);
    DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update(['sell_price_inc_tax' => $venda, 'default_sell_price' => $venda]);

    return $p;
}

/** Posto + roteiro + etapas + vínculo com o produto, como a tela de Roteiros grava. */
function pprRoteiro(int $businessId, int $productId, array $etapas, ?int $roteiroBusinessId = null): int
{
    $dono = $roteiroBusinessId ?? $businessId;
    $roteiroId = DB::table('pcp_roteiros')->insertGetId([
        'business_id' => $dono, 'familia' => 'Banner', 'nome' => 'Banner impresso ' . uniqid(), 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($etapas as $i => [$nome, $posto, $setor, $bloco, $trilha]) {
        $postoId = $posto === null ? null : DB::table('pcp_postos')->insertGetId([
            'business_id' => $dono, 'codigo' => 'PPR-' . uniqid(), 'nome' => $posto, 'setor' => $setor,
            'tipo_custo' => 'maquina', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pcp_roteiro_etapas')->insert([
            'business_id' => $dono, 'roteiro_id' => $roteiroId, 'bloco' => $bloco, 'trilha' => $trilha, 'ordem' => $i,
            'nome' => $nome, 'posto_id' => $postoId, 'horas' => 1.5, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    DB::table('pcp_produto_roteiro')->insert([
        'business_id' => $businessId, 'product_id' => $productId, 'roteiro_id' => $roteiroId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $roteiroId;
}

// ─── UC-PIDX-28 · preço proposto pela compra ────────────────────────────────

it('UC-PIDX-28 · a linha mostra o preço proposto que aguarda aprovação, e o painel a compra de origem', function () {
    if (! Schema::hasTable('compras_propostas_preco')) {
        $this->markTestSkipped('Módulo Compras sem migration no schema deste ambiente.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations', 'access_default_selling_price', 'purchase.view']);
    $p = pprProdutoComPreco($this->business->id, 16.5);
    $prop = pprProposta($this->business->id, $p->variations[0]['variation_id'], 16.5, 18.15);

    $linha = pprLinha($this, $p);
    expect($linha['preco_proposto'])->toEqual(['quantidade' => 1, 'preco' => 18.15]);
    // A lista avisa, não muda o preço: o preço de venda continua o gravado.
    expect((float) $linha['venda_max'])->toEqual(16.5);

    $itens = pprProp($this, '/products?ver=' . $p->productId, 'detalhe')['precos_propostos'];
    expect($itens)->toHaveCount(1);
    expect($itens[0]['id'])->toBe($prop['id']);
    expect([$itens[0]['preco_atual'], $itens[0]['preco_proposto'], $itens[0]['preco_mudou']])->toEqual([16.5, 18.15, false]);
    expect($itens[0]['compra_ref'])->toBe($prop['ref']);
    expect($itens[0]['compra_data'])->toBe('2026-10-12');
    expect($itens[0]['compra_url'])->toBe('/purchases/' . $prop['compra_id']);
});

it('UC-PIDX-28 · proposta já decidida não aparece; preço mudado depois da proposta vem marcado', function () {
    if (! Schema::hasTable('compras_propostas_preco')) {
        $this->markTestSkipped('Módulo Compras sem migration no schema deste ambiente.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations', 'access_default_selling_price']);
    $decidido = pprProdutoComPreco($this->business->id, 16.5);
    pprProposta($this->business->id, $decidido->variations[0]['variation_id'], 16.5, 18.15, 'aprovada');
    pprProposta($this->business->id, $decidido->variations[0]['variation_id'], 16.5, 19.0, 'recusada');

    expect(pprLinha($this, $decidido)['preco_proposto'])->toBeNull();
    expect(pprProp($this, '/products?ver=' . $decidido->productId, 'detalhe')['precos_propostos'])->toBe([]);

    $mudou = pprProdutoComPreco($this->business->id, 17.0);
    pprProposta($this->business->id, $mudou->variations[0]['variation_id'], 16.5, 18.15);
    $item = pprProp($this, '/products?ver=' . $mudou->productId, 'detalhe')['precos_propostos'][0];
    expect($item['preco_mudou'])->toBeTrue();
    // Sem `purchase.view` o link da compra não vem (botão sem efeito é proibido pelo charter).
    expect($item['compra_url'])->toBeNull();
});

it('UC-PIDX-28 · sem permissão de ver o preço de venda as chaves não vêm', function () {
    if (! Schema::hasTable('compras_propostas_preco')) {
        $this->markTestSkipped('Módulo Compras sem migration no schema deste ambiente.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations']);
    $p = pprProdutoComPreco($this->business->id, 16.5);
    pprProposta($this->business->id, $p->variations[0]['variation_id'], 16.5, 18.15);

    $linha = pprLinha($this, $p);
    // Anti-vácuo: a linha veio; a ausência é do gate.
    expect($linha)->toHaveKey('sku');
    expect($linha)->not->toHaveKey('preco_proposto');
    expect(pprProp($this, '/products?ver=' . $p->productId, 'detalhe'))->not->toHaveKey('precos_propostos');
});

it('UC-PIDX-28 · proposta gravada com outra empresa não aparece (Tier 0)', function () {
    if (! Schema::hasTable('compras_propostas_preco')) {
        $this->markTestSkipped('Módulo Compras sem migration no schema deste ambiente.');
    }
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations', 'access_default_selling_price']);
    $p = pprProdutoComPreco($this->business->id, 16.5);
    pprProposta($this->business->id, $p->variations[0]['variation_id'], 16.5, 18.15, 'pendente', $outra);

    expect(pprLinha($this, $p)['preco_proposto'])->toBeNull();
    expect(pprProp($this, '/products?ver=' . $p->productId, 'detalhe')['precos_propostos'])->toBe([]);
});

// ─── UC-PIDX-29 · roteiro com o setor de cada etapa ─────────────────────────

it('UC-PIDX-29 · o painel mostra o roteiro do produto, na ordem, com o posto e o setor de cada etapa', function () {
    if (! Schema::hasTable('pcp_produto_roteiro')) {
        $this->markTestSkipped('Módulo Produção sem migration no schema deste ambiente.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    // Gravadas fora de ordem de propósito: a ordem é a do roteiro (bloco, trilha), não a de gravação.
    pprRoteiro($this->business->id, $p->productId, [
        ['Acabamento', 'Mesa de acabamento', 'Acabamento', 3, 0],
        ['Arte', 'Estação de arte', 'Criação', 1, 0],
        ['Impressão', 'Plotter 1,60 m', 'Impressão digital', 2, 0],
        ['Expedição', null, null, 4, 0],
    ]);

    $r = pprProp($this, '/products?ver=' . $p->productId, 'detalhe')['roteiro'];
    expect($r)->not->toBeNull();
    expect(array_column($r['etapas'], 'nome'))->toBe(['Arte', 'Impressão', 'Acabamento', 'Expedição']);
    expect(array_column($r['etapas'], 'setor'))->toBe(['Criação', 'Impressão digital', 'Acabamento', null]);
    expect(array_column($r['etapas'], 'posto'))->toBe(['Estação de arte', 'Plotter 1,60 m', 'Mesa de acabamento', null]);
});

it('UC-PIDX-29 · produto sem roteiro não traz roteiro', function () {
    if (! Schema::hasTable('pcp_produto_roteiro')) {
        $this->markTestSkipped('Módulo Produção sem migration no schema deste ambiente.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);

    $d = pprProp($this, '/products?ver=' . $p->productId, 'detalhe');
    // Anti-vácuo: o painel veio; a ausência é do roteiro.
    expect($d['id'])->toBe($p->productId);
    expect($d['roteiro'])->toBeNull();
});

it('UC-PIDX-29 · vínculo apontando roteiro de outra empresa conta como sem roteiro (Tier 0)', function () {
    if (! Schema::hasTable('pcp_produto_roteiro')) {
        $this->markTestSkipped('Módulo Produção sem migration no schema deste ambiente.');
    }
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    pprEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    pprRoteiro($this->business->id, $p->productId, [['Arte', 'Estação de arte', 'Criação', 1, 0]], $outra);

    expect(pprProp($this, '/products?ver=' . $p->productId, 'detalhe')['roteiro'])->toBeNull();
});
