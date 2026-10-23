<?php

declare(strict_types=1);
// Cobre UC-PIDX-30, UC-PIDX-31 e UC-PIDX-32 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Consulta de produto (`/products` → `Produto/Index`) mostra três fluxos do Wagner:
 *
 *  UC-PIDX-30 · UC-FLX-19 — a requisição de compra aberta do produto (a que o estoque mínimo cria,
 *    US-COM-027, ou uma feita à mão), com as permissões da lista de requisições.
 *  UC-PIDX-31 · UC-FLX-31 — a natureza fiscal decidida pelo contador (`nfe_naturezas_produto`,
 *    US-NFE-068): selo na linha, filtro e a natureza na aba Fiscal. Sem decisão = mercadoria.
 *  UC-PIDX-32 · UC-FLX-38 — o método de custo do insumo no painel, só quando a empresa gravou a
 *    regra "Atualizar o custo dos insumos" e ela dispara no Recebido (US-COM-024/026).
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

function prnEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function prnProp($teste, string $url, string $prop): mixed
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
function prnLinhas($teste, array $query = []): array
{
    return pprnSemPaginar(prnProp($teste, '/products?' . http_build_query($query + ['q' => 'ESTFIX-', 'per_page' => -1]), 'lista'));
}

function pprnSemPaginar(array $lista): array
{
    return collect($lista['data'])->keyBy('id')->all();
}

function prnLinha($teste, object $p): array
{
    $sku = (string) DB::table('products')->where('id', $p->productId)->value('sku');
    $linhas = collect(prnProp($teste, '/products?' . http_build_query(['q' => $sku]), 'lista')['data'])->where('id', $p->productId)->values();
    expect($linhas)->toHaveCount(1);

    return $linhas[0];
}

/** Requisição de compra do núcleo com uma linha do produto, como a tela de requisições ou o job grava. */
function prnRequisicao(int $businessId, object $p, string $status, ?int $fornecedorId, int $criadoPor, string $data): array
{
    $ref = 'RQ-PRN-' . uniqid();
    $id = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'type' => 'purchase_requisition', 'status' => $status, 'contact_id' => $fornecedorId,
        'transaction_date' => $data, 'ref_no' => $ref, 'final_total' => 0, 'total_before_tax' => 0, 'tax_amount' => 0,
        'discount_amount' => 0, 'created_by' => $criadoPor, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $id, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => 10, 'quantity_returned' => 0, 'pp_without_discount' => 0, 'purchase_price' => 0,
        'purchase_price_inc_tax' => 0, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['id' => $id, 'ref' => $ref];
}

function prnFornecedor(int $businessId, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'supplier', 'name' => $nome, 'supplier_business_name' => $nome, 'mobile' => '',
        'contact_id' => 'PRN-' . uniqid(), 'created_by' => EstoqueFixture::userId($businessId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function prnNatureza(int $businessId, int $productId, string $natureza, ?string $codigo = null): void
{
    DB::table('nfe_naturezas_produto')->insert([
        'business_id' => $businessId, 'product_id' => $productId, 'natureza' => $natureza, 'codigo_servico' => $codigo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** A regra "Atualizar o custo dos insumos" gravada pela empresa, como a tela Fluxos grava. */
function prnRegraDeCusto(int $businessId, array $params, bool $ativo = true): void
{
    DB::table('fsm_modelos_empresa')->where('business_id', $businessId)->delete();
    DB::table('fsm_modelos_empresa')->insert([
        'business_id' => $businessId, 'modelo' => 'legado', 'created_at' => now(), 'updated_at' => now(),
        'regras_compra' => json_encode(['insumo.custo' => ['ativo' => $ativo, 'params' => $params]]),
    ]);
}

function prnCompraRecebida(int $businessId, object $p, string $data, float $preco): void
{
    $id = DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => $data, 'ref_no' => 'PRN-C-' . uniqid(), 'final_total' => 10 * $preco, 'total_before_tax' => 10 * $preco,
        'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => EstoqueFixture::userId($businessId),
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $id, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => 10, 'quantity_returned' => 0, 'pp_without_discount' => $preco, 'purchase_price' => $preco,
        'purchase_price_inc_tax' => $preco, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

// ─── UC-PIDX-30 · requisição de compra aberta ──────────────────────────────

it('UC-PIDX-30 · a linha e o painel mostram a requisição aberta mais recente, com número, data e fornecedor', function () {
    prnEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    $forn = prnFornecedor($this->business->id, 'Oracal PRN');
    prnRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-10 08:00:00');
    $nova = prnRequisicao($this->business->id, $p, 'partial', $forn, $this->user->id, '2026-10-21 08:00:00');
    // Requisição já atendida não conta.
    prnRequisicao($this->business->id, $p, 'completed', $forn, $this->user->id, '2026-10-22 08:00:00');

    $esperado = ['id' => $nova['id'], 'numero' => $nova['ref'], 'data' => '2026-10-21', 'fornecedor' => 'Oracal PRN', 'url' => '/purchase-requisition/' . $nova['id']];
    expect(prnLinha($this, $p)['requisicao'])->toEqual($esperado);
    expect(prnProp($this, '/products?ver=' . $p->productId, 'detalhe')['requisicao'])->toEqual($esperado);
});

it('UC-PIDX-30 · sem requisição aberta vem null; sem permissão de ver requisições a chave não vem', function () {
    prnEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    expect(prnLinha($this, $p))->toHaveKey('requisicao');
    expect(prnLinha($this, $p)['requisicao'])->toBeNull();

    prnRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-21 08:00:00');
    prnEntrar($this, ['product.view', 'access_all_locations']);
    $linha = prnLinha($this, $p);
    // Anti-vácuo: a linha veio; a ausência é do gate.
    expect($linha)->toHaveKey('sku');
    expect($linha)->not->toHaveKey('requisicao');
    expect(prnProp($this, '/products?ver=' . $p->productId, 'detalhe'))->not->toHaveKey('requisicao');
});

it('UC-PIDX-30 · com view_own só aparece a requisição que a própria pessoa criou', function () {
    prnEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_own']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    $outroUsuario = EstoqueFixture::userId($this->business->id);
    expect($outroUsuario)->not->toBe($this->user->id);
    prnRequisicao($this->business->id, $p, 'ordered', null, $outroUsuario, '2026-10-20 08:00:00');
    expect(prnLinha($this, $p)['requisicao'])->toBeNull();

    $minha = prnRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-19 08:00:00');
    expect(prnLinha($this, $p)['requisicao']['id'])->toBe($minha['id']);
});

it('UC-PIDX-30 · requisição de outra empresa não aparece (Tier 0)', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    prnEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    prnRequisicao($outra, $p, 'ordered', null, EstoqueFixture::userId($outra), '2026-10-21 08:00:00');

    expect(prnLinha($this, $p)['requisicao'])->toBeNull();
});

// ─── UC-PIDX-31 · natureza fiscal ──────────────────────────────────────────

it('UC-PIDX-31 · a linha traz a natureza decidida; sem decisão é mercadoria; o painel mostra o item da LC 116', function () {
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Módulo Fiscal sem migration no schema deste ambiente.');
    }
    prnEntrar($this, ['product.view', 'access_all_locations']);
    $merc = EstoqueFixture::singleProduct($this->business->id);
    $serv = EstoqueFixture::singleProduct($this->business->id);
    $pend = EstoqueFixture::singleProduct($this->business->id);
    prnNatureza($this->business->id, $serv->productId, 'servico', '24.01');
    prnNatureza($this->business->id, $pend->productId, 'pendente');

    expect([prnLinha($this, $merc)['natureza_fiscal'], prnLinha($this, $serv)['natureza_fiscal'], prnLinha($this, $pend)['natureza_fiscal']])
        ->toBe(['mercadoria', 'servico', 'pendente']);
    expect(prnProp($this, '/products?ver=' . $serv->productId, 'detalhe')['fiscal']['natureza'])
        ->toEqual(['natureza' => 'servico', 'codigo_servico' => '24.01']);
    expect(prnProp($this, '/products?ver=' . $merc->productId, 'detalhe')['fiscal']['natureza'])
        ->toEqual(['natureza' => 'mercadoria', 'codigo_servico' => null]);
});

it('UC-PIDX-31 · o filtro "Natureza fiscal" é resolvido no servidor, nas três opções', function () {
    if (! Schema::hasTable('nfe_naturezas_produto')) {
        $this->markTestSkipped('Módulo Fiscal sem migration no schema deste ambiente.');
    }
    prnEntrar($this, ['product.view', 'access_all_locations']);
    $merc = EstoqueFixture::singleProduct($this->business->id);
    $serv = EstoqueFixture::singleProduct($this->business->id);
    $pend = EstoqueFixture::singleProduct($this->business->id);
    prnNatureza($this->business->id, $serv->productId, 'servico', '24.01');
    prnNatureza($this->business->id, $pend->productId, 'pendente');
    $nossos = [$merc->productId, $serv->productId, $pend->productId];
    $ids = fn (array $q) => array_values(array_intersect($nossos, array_keys(prnLinhas($this, $q))));

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
    prnEntrar($this, ['product.view', 'access_all_locations']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    prnNatureza($outra, $p->productId, 'pendente');

    expect(prnLinha($this, $p)['natureza_fiscal'])->toBe('mercadoria');
    $ids = array_keys(prnLinhas($this, ['natureza_fiscal' => 'pendente']));
    expect($ids)->not->toContain($p->productId);
});

// ─── UC-PIDX-32 · método de custo do insumo ────────────────────────────────

it('UC-PIDX-32 · com a regra de custo gravada no Recebido, cada variação traz o método e a data da última compra', function () {
    prnEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    prnCompraRecebida($this->business->id, $p, '2026-09-20 09:00:00', 11.0);
    prnCompraRecebida($this->business->id, $p, '2026-10-12 09:00:00', 13.2);
    prnRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);

    $v = prnProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0];
    expect($v['custo_metodo'])->toEqual(['metodo' => 'medio', 'ultima_compra_em' => '2026-10-12']);

    prnRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    expect(prnProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0]['custo_metodo']['metodo'])->toBe('ultimo');
});

it('UC-PIDX-32 · sem a regra gravada, com a regra em Conferido, desligada ou sem ver custo, o método não vem', function () {
    prnEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    prnCompraRecebida($this->business->id, $p, '2026-10-12 09:00:00', 13.2);
    DB::table('fsm_modelos_empresa')->where('business_id', $this->business->id)->delete();
    $variacao = fn () => prnProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0];

    // Anti-vácuo: o custo vem; a ausência é do método.
    expect($variacao())->toHaveKey('compra_inc');
    expect($variacao())->not->toHaveKey('custo_metodo');

    prnRegraDeCusto($this->business->id, ['etapa' => 'conferido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    expect($variacao())->not->toHaveKey('custo_metodo');

    prnRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento'], false);
    expect($variacao())->not->toHaveKey('custo_metodo');

    prnRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    prnEntrar($this, ['product.view', 'access_all_locations']);
    expect($variacao())->not->toHaveKey('custo_metodo');
});
