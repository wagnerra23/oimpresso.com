<?php

declare(strict_types=1);
// Cobre UC-PIDX-30 (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Consulta de produto (`/products` → `Produto/Index`): a requisição de compra aberta do produto.
 *
 *  UC-PIDX-30 · UC-FLX-19 — a requisição aberta (a que o estoque mínimo cria, US-COM-027, ou uma
 *    feita à mão), com as permissões da lista de requisições.
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

function prqEntrar($teste, array $permissoes): void
{
    $teste->user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($teste->user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $teste->user->id, 'business.id' => $teste->business->id]);
    session(['currency' => ['symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','], 'business.date_format' => 'd/m/Y']);
}

function prqProp($teste, string $url, string $prop): mixed
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

function prqLinha($teste, object $p): array
{
    $sku = (string) DB::table('products')->where('id', $p->productId)->value('sku');
    $linhas = collect(prqProp($teste, '/products?' . http_build_query(['q' => $sku]), 'lista')['data'])->where('id', $p->productId)->values();
    expect($linhas)->toHaveCount(1);

    return $linhas[0];
}

/** Requisição de compra do núcleo com uma linha do produto, como a tela de requisições ou o job grava. */
function prqRequisicao(int $businessId, object $p, string $status, ?int $fornecedorId, int $criadoPor, string $data): array
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

function prqFornecedor(int $businessId, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'supplier', 'name' => $nome, 'supplier_business_name' => $nome, 'mobile' => '',
        'contact_id' => 'PRN-' . uniqid(), 'created_by' => EstoqueFixture::userId($businessId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

// ─── UC-PIDX-30 · requisição de compra aberta ──────────────────────────────

it('UC-PIDX-30 · a linha e o painel mostram a requisição aberta mais recente, com número, data e fornecedor', function () {
    prqEntrar($this, ['product.view', 'access_all_locations', 'stock_report.view', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    $forn = prqFornecedor($this->business->id, 'Oracal PRN');
    prqRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-10 08:00:00');
    $nova = prqRequisicao($this->business->id, $p, 'partial', $forn, $this->user->id, '2026-10-21 08:00:00');
    // Requisição já atendida não conta.
    prqRequisicao($this->business->id, $p, 'completed', $forn, $this->user->id, '2026-10-22 08:00:00');

    $esperado = ['id' => $nova['id'], 'numero' => $nova['ref'], 'data' => '2026-10-21', 'fornecedor' => 'Oracal PRN', 'url' => '/purchase-requisition/' . $nova['id']];
    expect(prqLinha($this, $p)['requisicao'])->toEqual($esperado);
    expect(prqProp($this, '/products?ver=' . $p->productId, 'detalhe')['requisicao'])->toEqual($esperado);
});

it('UC-PIDX-30 · sem requisição aberta vem null; sem permissão de ver requisições a chave não vem', function () {
    prqEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    expect(prqLinha($this, $p))->toHaveKey('requisicao');
    expect(prqLinha($this, $p)['requisicao'])->toBeNull();

    prqRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-21 08:00:00');
    prqEntrar($this, ['product.view', 'access_all_locations']);
    $linha = prqLinha($this, $p);
    // Anti-vácuo: a linha veio; a ausência é do gate.
    expect($linha)->toHaveKey('sku');
    expect($linha)->not->toHaveKey('requisicao');
    expect(prqProp($this, '/products?ver=' . $p->productId, 'detalhe'))->not->toHaveKey('requisicao');
});

it('UC-PIDX-30 · com view_own só aparece a requisição que a própria pessoa criou', function () {
    prqEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_own']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    $outroUsuario = EstoqueFixture::userId($this->business->id);
    expect($outroUsuario)->not->toBe($this->user->id);
    prqRequisicao($this->business->id, $p, 'ordered', null, $outroUsuario, '2026-10-20 08:00:00');
    expect(prqLinha($this, $p)['requisicao'])->toBeNull();

    $minha = prqRequisicao($this->business->id, $p, 'ordered', null, $this->user->id, '2026-10-19 08:00:00');
    expect(prqLinha($this, $p)['requisicao']['id'])->toBe($minha['id']);
});

it('UC-PIDX-30 · requisição de outra empresa não aparece (Tier 0)', function () {
    $outra = EstoqueFixture::secondBusinessId();
    if ($outra === null) {
        $this->markTestSkipped('Segundo tenant não semeado.');
    }
    prqEntrar($this, ['product.view', 'access_all_locations', 'purchase_requisition.view_all']);
    $p = EstoqueFixture::singleProduct($this->business->id);
    prqRequisicao($outra, $p, 'ordered', null, EstoqueFixture::userId($outra), '2026-10-21 08:00:00');

    expect(prqLinha($this, $p)['requisicao'])->toBeNull();
});
