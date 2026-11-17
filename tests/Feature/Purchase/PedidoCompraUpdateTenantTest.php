<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Compras\Services\AlcadaDoPedido;
use Spatie\Permission\Models\Permission;

/**
 * Contrato do `PurchaseOrderController@update` (auditoria Compras 2026-11-05, lote 0 PR 0.3).
 *
 * UC-PEDC-06 `[T0]` (A1) — o update resolvia o pedido com `Transaction::findOrFail($id)` sem
 *   `business_id`: um usuário editava o pedido de outra empresa pelo id da URL.
 * UC-PEDC-07 `[T0]` (A7) — a alçada (US-COM-032) só rodava quando o pedido nascia. Subir o valor na
 *   edição mantinha o pedido livre para virar compra recebida.
 *
 * Tenants: usuário no 98 (fictício, ADR 0358); vítima no 99 (`seededSupportClientTenant()`). Nunca 4.
 *
 * Os requests usam `actingAs()` PURO, sem `withSession(['user' => ...])`: o middleware
 * SetSessionData só popula `business.date_format` quando o bloco `user` não vem pronto, e sem ele
 * `uf_date` estoura antes do ponto que o teste mede (mesma nota de UpdateCrossTenantIdorTest).
 *
 * @covers-uc UC-PEDC-06
 * @covers-uc UC-PEDC-07
 *
 * @see app/Http/Controllers/PurchaseOrderController.php::update
 * @see Modules/Compras/Services/AlcadaDoPedido.php
 */
uses(DatabaseTransactions::class);

const PEDUPD_TENANT = 98;

function pedupdLocation(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDUPD Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDUPD Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'PEDUPD Loc ' . $bizId,
        'location_id' => strtoupper(substr(md5('pedupd' . $bizId . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pedupdPedido(int $bizId, int $locId, int $userId, string $total, string $ref): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => 'purchase_order', 'status' => 'ordered', 'ref_no' => $ref,
        'transaction_date' => now(), 'total_before_tax' => $total, 'final_total' => $total,
        'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Payload do form de edição. Valores sem separador: `num_uf('5000')` = 5000 em qualquer formato. */
function pedupdPayload(string $ref, string $total): array
{
    return [
        'ref_no' => $ref, 'transaction_date' => now()->format('m/d/Y H:i'),
        'total_before_tax' => $total, 'discount_type' => 'fixed', 'discount_amount' => '0',
        'tax_amount' => '0', 'shipping_charges' => '0', 'final_total' => $total,
        'exchange_rate' => 1, 'purchases' => [],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(PEDUPD_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $this->vitima->id, [PEDUPD_TENANT, 4]);

    $this->loc = pedupdLocation(PEDUPD_TENANT);
    $this->locVitima = pedupdLocation((int) $this->vitima->id);

    Permission::findOrCreate('purchase_order.update', 'web');
    $this->user = User::factory()->create([
        'business_id' => PEDUPD_TENANT, 'username' => 'ct_pedupd_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo('purchase_order.update');
    $this->donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_pedupd_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── UC-PEDC-06 ─────────────────────────────────────────────────────────────

it('UC-PEDC-06 (controle positivo) editar o PROPRIO pedido grava a referencia nova', function () {
    $id = pedupdPedido(PEDUPD_TENANT, $this->loc, (int) $this->user->id, '500', 'PEDUPD-OWN-' . uniqid());

    $this->actingAs($this->user)->put('/purchase-order/' . $id, pedupdPayload('PEDUPD-EDITADO', '500'));

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame('PEDUPD-EDITADO', DB::table('transactions')->where('id', $id)->value('ref_no'));
});

it('UC-PEDC-06 (contrato T0) editar pedido de OUTRA empresa nao grava nada', function () {
    $ref = 'PEDUPD-VIT-' . uniqid();
    $id = pedupdPedido((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id, '5000', $ref);

    $r = $this->actingAs($this->user)->put('/purchase-order/' . $id, pedupdPayload('HACKED', '1'));

    // 404 (não 403): não revela que o pedido existe em outra empresa. A busca escopada fica
    // fora do try do controller; antes ela ficava dentro e o catch genérico chamava
    // DB::rollBack() sem transação própria, desfazendo a transação do teste junto (job 127488764426).
    $this->assertSame(404, $r->status(), 'A edicao cross-tenant tem de responder 404. status=' . $r->status() . ' sessao=' . json_encode(session('status')));

    $depois = DB::table('transactions')->where('id', $id)->first();
    $this->assertNotNull($depois, 'O pedido da vitima sumiu: a fixture nao sobreviveu ao request.');
    $this->assertSame($ref, $depois->ref_no, 'VAZAMENTO TIER 0: a referencia do pedido do business ' . $this->vitima->id . ' mudou.');
    $this->assertSame(5000.0, (float) $depois->final_total, 'VAZAMENTO TIER 0: o total do pedido da vitima mudou.');
});

// ─── UC-PEDC-07 ─────────────────────────────────────────────────────────────

it('UC-PEDC-07 (contrato T0) subir o valor na edicao deixa o pedido aguardando aprovacao', function () {
    $this->assertTrue(Schema::hasTable('compras_aprovacoes_pedido'), 'Lane sem a tabela da alcada (migrations de Compras).');
    $svc = app(AlcadaDoPedido::class);
    $svc->definir(PEDUPD_TENANT, true, '1000.00', '10000.00');

    $id = pedupdPedido(PEDUPD_TENANT, $this->loc, (int) $this->user->id, '500', 'PEDUPD-ALC-' . uniqid());
    // 500,00 está abaixo do limite do comprador: nasce sem aprovação, livre para receber.
    $this->assertNull($svc->registrar(PEDUPD_TENANT, $id, (int) $this->user->id));
    $this->assertSame([], $svc->bloqueiaRecebimento(PEDUPD_TENANT, [$id]));

    $this->actingAs($this->user)->put('/purchase-order/' . $id, pedupdPayload('PEDUPD-ALC-5000', '5000'));

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame(5000.0, (float) DB::table('transactions')->where('id', $id)->value('final_total'));
    $aprovacao = DB::table('compras_aprovacoes_pedido')->where('business_id', PEDUPD_TENANT)->where('transaction_id', $id)->first();
    $this->assertNotNull($aprovacao, 'O valor subiu para 5.000,00 e a alcada nao rodou na edicao.');
    $this->assertSame('pendente', $aprovacao->status);
    $this->assertSame('gerente', $aprovacao->nivel);
    $this->assertSame([$id], $svc->bloqueiaRecebimento(PEDUPD_TENANT, [$id]), 'O pedido de 5.000,00 sem aprovacao ficou livre para receber.');
});

it('UC-PEDC-07 (controle) editar sem mudar o valor nao mexe na aprovacao', function () {
    $this->assertTrue(Schema::hasTable('compras_aprovacoes_pedido'), 'Lane sem a tabela da alcada (migrations de Compras).');
    $svc = app(AlcadaDoPedido::class);
    $svc->definir(PEDUPD_TENANT, true, '1000.00', '10000.00');

    $id = pedupdPedido(PEDUPD_TENANT, $this->loc, (int) $this->user->id, '500', 'PEDUPD-ALC2-' . uniqid());

    $this->actingAs($this->user)->put('/purchase-order/' . $id, pedupdPayload('PEDUPD-ALC2-REF', '500'));

    $this->assertSame(1, session('status')['success'] ?? null, 'msg=' . json_encode(session('status')));
    $this->assertSame(0, DB::table('compras_aprovacoes_pedido')->where('transaction_id', $id)->count());
});
