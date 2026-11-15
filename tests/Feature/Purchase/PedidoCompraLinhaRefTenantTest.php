<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato T0 das referências de linha no `PurchaseOrderController` (scorecard do ProductUtil,
 * rejulgamento de 2026-11-10: hipótese de leitura que este arquivo prova ou refuta rodando).
 *
 * `purchases.*.purchase_order_line_id` e `purchases.*.purchase_requisition_line_id` descem por
 * `ProductUtil::createOrUpdatePurchaseLines` até `updatePurchaseOrderLine`, que faz
 * `PurchaseLine::find` sem empresa e soma a quantidade em `po_quantity_purchased`.
 * `purchase_requisition_ids` desce até `TransactionUtil::updatePurchaseOrderStatus`, que recalcula
 * o status com `Transaction::find`, também sem empresa.
 *
 * O `store()` não conferia `purchase_order_line_id`; o `update()` não conferia nenhum dos três.
 *
 * Os controles positivos não são enfeite: cada um prova que o payload chega até a escrita (o
 * `po_quantity_purchased` da PRÓPRIA linha sobe de 0 para 4). Sem eles, um fixture que parasse na
 * validação deixaria a linha da vítima em 0 e o teste de ataque passaria por vácuo.
 *
 * Tenants: usuário no 98 (fictício, ADR 0358); vítima no 99 (`seededSupportClientTenant()`). Nunca 4.
 * Os requests usam `actingAs()` puro (ver a nota em PedidoCompraUpdateTenantTest).
 *
 * @covers-uc UC-PEDC-11
 *
 * @see app/Http/Controllers/PurchaseOrderController.php
 * @see app/Utils/ProductUtil.php::updatePurchaseOrderLine
 */
uses(DatabaseTransactions::class);

const PEDREF_TENANT = 98;

function pedrefLocation(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDREF Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDREF Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'PEDREF Loc ' . $bizId,
        'location_id' => strtoupper(substr(md5('pedref' . $bizId . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Produto com unidade e uma variação. Devolve [product_id, variation_id]. */
function pedrefProduto(int $bizId, int $userId): array
{
    $unit = DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'un',
        'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $prod = DB::table('products')->insertGetId([
        'name' => 'PEDREF Produto', 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'PEDREF' . uniqid(), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $var = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'PEDREF' . uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $prod, (int) $var];
}

/** Transação (`purchase_order` ou `purchase_requisition`) com uma linha. Devolve [transacao_id, linha_id]. */
function pedrefDocumento(int $bizId, int $locId, int $userId, string $tipo, int $recebido = 0): array
{
    [$prod, $var] = pedrefProduto($bizId, $userId);
    $doc = DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => $tipo, 'status' => 'ordered', 'ref_no' => 'PEDREF-DOC-' . uniqid(),
        'transaction_date' => now(), 'final_total' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $doc, 'product_id' => $prod, 'variation_id' => $var, 'quantity' => 10,
        'po_quantity_purchased' => $recebido, 'purchase_price' => 0, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $doc, (int) $linha];
}

function pedrefPedidoSemLinhas(int $bizId, int $locId, int $userId): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => 'purchase_order', 'status' => 'ordered', 'ref_no' => 'PEDREF-PED-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 0, 'final_total' => 0,
        'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Linha do formulário, nova (sem `purchase_line_id`), com a referência em teste. Quantidade 4. */
function pedrefLinhaForm(array $produto, array $referencia): array
{
    return array_replace([
        'product_id' => $produto[0], 'variation_id' => $produto[1], 'quantity' => '4',
        'pp_without_discount' => '0', 'discount_percent' => '0', 'purchase_price' => '0',
        'purchase_price_inc_tax' => '0', 'item_tax' => '0', 'purchase_line_tax_id' => '',
    ], $referencia);
}

function pedrefRecebido(int $linha): float
{
    return (float) DB::table('purchase_lines')->where('id', $linha)->value('po_quantity_purchased');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(PEDREF_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $this->vitima->id, [PEDREF_TENANT, 4]);

    // `store()` para em `isSubscribed` antes de gravar quando o tenant de teste não tem pacote.
    $this->partialMock(ModuleUtil::class, function ($m) {
        $m->shouldReceive('isSubscribed')->andReturn(true);
    });

    $this->loc = pedrefLocation(PEDREF_TENANT);
    $this->locVitima = pedrefLocation((int) $this->vitima->id);

    Permission::findOrCreate('purchase_order.create', 'web');
    Permission::findOrCreate('purchase_order.update', 'web');
    $this->user = User::factory()->create([
        'business_id' => PEDREF_TENANT, 'username' => 'ct_pedref_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo(['purchase_order.create', 'purchase_order.update']);
    $this->donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_pedref_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->produto = pedrefProduto(PEDREF_TENANT, (int) $this->user->id);
    $this->fornecedor = (int) DB::table('contacts')->insertGetId([
        'business_id' => PEDREF_TENANT, 'type' => 'supplier', 'name' => 'PEDREF Fornecedor',
        'contact_id' => 'PEDREF' . uniqid(), 'created_by' => (int) $this->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->criarPedido = function (array $linha) {
        return $this->actingAs($this->user)->post('/purchase-order', [
            'ref_no' => 'PEDREF-NOVO-' . uniqid(), 'contact_id' => $this->fornecedor,
            'transaction_date' => now()->format('m/d/Y H:i'), 'location_id' => $this->loc,
            'total_before_tax' => '0', 'discount_type' => 'fixed', 'discount_amount' => '0',
            'tax_amount' => '0', 'shipping_charges' => '0', 'final_total' => '0', 'exchange_rate' => 1,
            'purchases' => [$linha],
        ]);
    };

    $this->editarPedido = function (int $id, array $extra) {
        return $this->actingAs($this->user)->put('/purchase-order/' . $id, array_replace([
            'ref_no' => 'PEDREF-EDIT-' . uniqid(), 'transaction_date' => now()->format('m/d/Y H:i'),
            'total_before_tax' => '0', 'discount_type' => 'fixed', 'discount_amount' => '0',
            'tax_amount' => '0', 'shipping_charges' => '0', 'final_total' => '0', 'exchange_rate' => 1,
            'purchases' => [],
        ], $extra));
    };

    $this->linhasDoPedido = fn (int $id) => DB::table('purchase_lines')->where('transaction_id', $id)->count();
});

// ─── store() ────────────────────────────────────────────────────────────────

it('UC-PEDC-11 (controle) pedido novo com linha de pedido da PROPRIA empresa grava e soma na propria linha', function () {
    [, $linha] = pedrefDocumento(PEDREF_TENANT, $this->loc, (int) $this->user->id, 'purchase_order');

    ($this->criarPedido)(pedrefLinhaForm($this->produto, ['purchase_order_line_id' => $linha]));

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame(4.0, pedrefRecebido($linha), 'O payload nao chegou ate updatePurchaseOrderLine: o teste de ataque passaria por vacuo.');
});

it('UC-PEDC-11 (contrato T0) pedido novo com linha de pedido de OUTRA empresa e recusado e a linha nao muda', function () {
    [, $linha] = pedrefDocumento((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase_order');
    $antes = DB::table('transactions')->where('business_id', PEDREF_TENANT)->where('type', 'purchase_order')->count();

    $r = ($this->criarPedido)(pedrefLinhaForm($this->produto, ['purchase_order_line_id' => $linha]));

    $this->assertSame(0.0, pedrefRecebido($linha), 'VAZAMENTO TIER 0: o store somou quantidade na linha de pedido do business ' . $this->vitima->id . '.');
    $this->assertSame(422, $r->status(), 'status=' . $r->status() . ' sessao=' . json_encode(session('status')));
    $this->assertSame($antes, DB::table('transactions')->where('business_id', PEDREF_TENANT)->where('type', 'purchase_order')->count(), 'Um pedido nasceu apesar da recusa.');
});

// ─── update() ───────────────────────────────────────────────────────────────

it('UC-PEDC-11 (controle) editar com linha de requisicao e requisicao da PROPRIA empresa grava e soma na propria linha', function () {
    [$req, $linha] = pedrefDocumento(PEDREF_TENANT, $this->loc, (int) $this->user->id, 'purchase_requisition');
    $id = pedrefPedidoSemLinhas(PEDREF_TENANT, $this->loc, (int) $this->user->id);

    ($this->editarPedido)($id, [
        'purchase_requisition_ids' => [$req],
        'purchases' => [pedrefLinhaForm($this->produto, ['purchase_requisition_line_id' => $linha])],
    ]);

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame(4.0, pedrefRecebido($linha), 'O payload nao chegou ate updatePurchaseOrderLine: o teste de ataque passaria por vacuo.');
    $this->assertSame(1, ($this->linhasDoPedido)($id));
    $this->assertSame('partial', DB::table('transactions')->where('id', $req)->value('status'), 'O status da requisicao da propria empresa nao foi recalculado.');
});

it('UC-PEDC-11 (contrato T0) editar com linha de requisicao de OUTRA empresa e recusado e a linha nao muda', function () {
    [, $linha] = pedrefDocumento((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase_requisition');
    $id = pedrefPedidoSemLinhas(PEDREF_TENANT, $this->loc, (int) $this->user->id);

    $r = ($this->editarPedido)($id, ['purchases' => [pedrefLinhaForm($this->produto, ['purchase_requisition_line_id' => $linha])]]);

    $this->assertSame(0.0, pedrefRecebido($linha), 'VAZAMENTO TIER 0: o update somou quantidade na linha de requisicao do business ' . $this->vitima->id . '.');
    $this->assertSame(422, $r->status(), 'status=' . $r->status() . ' sessao=' . json_encode(session('status')));
    $this->assertSame(0, ($this->linhasDoPedido)($id), 'Uma linha foi gravada apesar da recusa.');
});

it('UC-PEDC-11 (contrato T0) editar com linha de pedido de OUTRA empresa e recusado e a linha nao muda', function () {
    [, $linha] = pedrefDocumento((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase_order');
    $id = pedrefPedidoSemLinhas(PEDREF_TENANT, $this->loc, (int) $this->user->id);

    $r = ($this->editarPedido)($id, ['purchases' => [pedrefLinhaForm($this->produto, ['purchase_order_line_id' => $linha])]]);

    $this->assertSame(0.0, pedrefRecebido($linha), 'VAZAMENTO TIER 0: o update somou quantidade na linha de pedido do business ' . $this->vitima->id . '.');
    $this->assertSame(422, $r->status(), 'status=' . $r->status() . ' sessao=' . json_encode(session('status')));
    $this->assertSame(0, ($this->linhasDoPedido)($id), 'Uma linha foi gravada apesar da recusa.');
});

it('UC-PEDC-11 (contrato T0) editar apontando requisicao de OUTRA empresa e recusado e o status dela nao muda', function () {
    // 10 de 10 já atendidos: o recálculo a levaria de `ordered` para `completed`.
    [$req] = pedrefDocumento((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, 'purchase_requisition', 10);
    $id = pedrefPedidoSemLinhas(PEDREF_TENANT, $this->loc, (int) $this->user->id);

    $r = ($this->editarPedido)($id, ['purchase_requisition_ids' => [$req]]);

    $this->assertSame('ordered', DB::table('transactions')->where('id', $req)->value('status'), 'VAZAMENTO TIER 0: o update recalculou o status da requisicao do business ' . $this->vitima->id . '.');
    $this->assertSame(422, $r->status(), 'status=' . $r->status() . ' sessao=' . json_encode(session('status')));
});
