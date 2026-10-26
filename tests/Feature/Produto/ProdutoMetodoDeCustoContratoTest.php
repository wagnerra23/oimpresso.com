<?php

declare(strict_types=1);
// Cobre UC-PIDX-32 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Consulta de produto (`/products` → `Produto/Index`): o método de custo do insumo.
 *
 *  UC-PIDX-32 · UC-FLX-38 — o método de custo no painel, só quando a empresa gravou a regra
 *    "Atualizar o custo dos insumos" e ela dispara no Recebido (US-COM-024/026).
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

function pmcEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function pmcProp($teste, string $url, string $prop): mixed
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

/** A regra "Atualizar o custo dos insumos" gravada pela empresa, como a tela Fluxos grava. */
function pmcRegraDeCusto(int $businessId, array $params, bool $ativo = true): void
{
    DB::table('fsm_modelos_empresa')->where('business_id', $businessId)->delete();
    DB::table('fsm_modelos_empresa')->insert([
        'business_id' => $businessId, 'modelo' => 'legado', 'created_at' => now(), 'updated_at' => now(),
        'regras_compra' => json_encode(['insumo.custo' => ['ativo' => $ativo, 'params' => $params]]),
    ]);
}

function pmcCompraRecebida(int $businessId, object $p, string $data, float $preco): void
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

// ─── UC-PIDX-32 · método de custo do insumo ────────────────────────────────

it('UC-PIDX-32 · com a regra de custo gravada no Recebido, cada variação traz o método e a data da última compra', function () {
    pmcEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    pmcCompraRecebida($this->business->id, $p, '2026-09-20 09:00:00', 11.0);
    pmcCompraRecebida($this->business->id, $p, '2026-10-12 09:00:00', 13.2);
    pmcRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);

    $v = pmcProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0];
    expect($v['custo_metodo'])->toEqual(['metodo' => 'medio', 'ultima_compra_em' => '2026-10-12']);

    pmcRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'ultimo', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    expect(pmcProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0]['custo_metodo']['metodo'])->toBe('ultimo');
});

it('UC-PIDX-32 · sem a regra gravada, com a regra em Conferido, desligada ou sem ver custo, o método não vem', function () {
    pmcEntrar($this, ['product.view', 'access_all_locations', 'view_purchase_price']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    pmcCompraRecebida($this->business->id, $p, '2026-10-12 09:00:00', 13.2);
    DB::table('fsm_modelos_empresa')->where('business_id', $this->business->id)->delete();
    $variacao = fn () => pmcProp($this, '/products?ver=' . $p->productId, 'detalhe')['variacoes'][0];

    // Anti-vácuo: o custo vem; a ausência é do método.
    expect($variacao())->toHaveKey('compra_inc');
    expect($variacao())->not->toHaveKey('custo_metodo');

    pmcRegraDeCusto($this->business->id, ['etapa' => 'conferido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    expect($variacao())->not->toHaveKey('custo_metodo');

    pmcRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento'], false);
    expect($variacao())->not->toHaveKey('custo_metodo');

    pmcRegraDeCusto($this->business->id, ['etapa' => 'recebido', 'metodo' => 'medio', 'preco' => 'so_custo', 'aprovacao' => 'recebimento']);
    pmcEntrar($this, ['product.view', 'access_all_locations']);
    expect($variacao())->not->toHaveKey('custo_metodo');
});
