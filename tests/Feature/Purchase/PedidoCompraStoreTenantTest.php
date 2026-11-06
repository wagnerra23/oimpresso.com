<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Contrato T0 do `PurchaseOrderController@store` (auditoria Compras 2026-11-05, lote 0 PR 0.4, A2).
 *
 * O pedido novo aceitava, crus: `purchase_requisition_ids` (o status da requisição de outra empresa
 * era recalculado), `purchases.*.purchase_requisition_line_id` (a quantidade pedida era somada na
 * linha da requisição de outra empresa) e `purchases.*.purchase_line_id` (a linha de compra de outra
 * empresa era reaproveitada pelo id).
 *
 * Tenants: usuário no 98 (fictício, ADR 0358); vítima no 99 (`seededSupportClientTenant()`). Nunca 4.
 *
 * @covers-uc UC-PEDC-08
 *
 * @see app/Http/Controllers/PurchaseOrderController.php::store
 */
uses(DatabaseTransactions::class);

const PEDSTO_TENANT = 98;

function pedstoLocation(int $bizId): int
{
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDSTO Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'PEDSTO Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'PEDSTO Loc ' . $bizId,
        'location_id' => strtoupper(substr(md5('pedsto' . $bizId . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Requisição com uma linha. Devolve [requisicao_id, linha_id]. */
function pedstoRequisicao(int $bizId, int $locId, int $userId): array
{
    $unit = DB::table('units')->insertGetId([
        'business_id' => $bizId, 'actual_name' => 'Unidade', 'short_name' => 'un',
        'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $prod = DB::table('products')->insertGetId([
        'name' => 'PEDSTO Produto', 'business_id' => $bizId, 'type' => 'single', 'unit_id' => $unit,
        'tax_type' => 'exclusive', 'sku' => 'PEDSTO' . uniqid(), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $var = DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'product_variation_id' => $pv, 'sub_sku' => 'PEDSTO' . uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $req = DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => $locId, 'created_by' => $userId,
        'type' => 'purchase_requisition', 'status' => 'ordered', 'ref_no' => 'PEDSTO-REQ-' . uniqid(),
        'transaction_date' => now(), 'final_total' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linha = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $req, 'product_id' => $prod, 'variation_id' => $var, 'quantity' => 10,
        'po_quantity_purchased' => 0, 'purchase_price' => 0, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [(int) $req, (int) $linha];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Precisa do schema UltimatePOS em MySQL real (lane purchase-pest.yml).');
    }

    $this->assertNotNull(\App\Business::find(PEDSTO_TENANT), 'Lane sem tenant 98 semeado (ADR 0358).');
    $this->vitima = $this->seededSupportClientTenant();
    $this->assertNotContains((int) $this->vitima->id, [PEDSTO_TENANT, 4]);

    $this->loc = pedstoLocation(PEDSTO_TENANT);
    $this->locVitima = pedstoLocation((int) $this->vitima->id);

    Permission::findOrCreate('purchase_order.create', 'web');
    $this->user = User::factory()->create([
        'business_id' => PEDSTO_TENANT, 'username' => 'ct_pedsto_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $this->user->givePermissionTo('purchase_order.create');
    $this->donoVitima = User::factory()->create([
        'business_id' => (int) $this->vitima->id, 'username' => 'ct_pedsto_v_' . uniqid(),
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Payload incompleto de propósito (sem contact_id): as travas de posse rodam antes da
    // validação; quando nenhuma dispara, o request para na validação e nada é gravado.
    $this->enviar = function (array $extra) {
        $payload = array_replace(['location_id' => $this->loc, 'purchases' => []], $extra);
        try {
            $this->withoutExceptionHandling()->actingAs($this->user)->post('/purchase-order', $payload);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    };
    $this->pedidosAntes = DB::table('transactions')->where('business_id', PEDSTO_TENANT)->where('type', 'purchase_order')->count();
});

function pedstoAssert422($test, ?\Throwable $erro, string $trecho): void
{
    $test->assertInstanceOf(HttpException::class, $erro, 'Esperava 422 da trava; veio: ' . ($erro ? get_class($erro) . ' ' . $erro->getMessage() : 'nada'));
    $test->assertSame(422, $erro->getStatusCode());
    $test->assertStringContainsString($trecho, $erro->getMessage());
    $test->assertSame(
        $test->pedidosAntes,
        DB::table('transactions')->where('business_id', PEDSTO_TENANT)->where('type', 'purchase_order')->count(),
        'Um pedido nasceu apesar da recusa.'
    );
}

it('UC-PEDC-08 (contrato T0) requisicao de outra empresa e recusada e o status dela nao muda', function () {
    [$req] = pedstoRequisicao((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id);

    $erro = ($this->enviar)(['purchase_requisition_ids' => [$req]]);

    pedstoAssert422($this, $erro, 'Requisição de compra inválida');
    $this->assertSame('ordered', DB::table('transactions')->where('id', $req)->value('status'));
});

it('UC-PEDC-08 (contrato T0) linha de requisicao de outra empresa e recusada e a quantidade dela nao muda', function () {
    [, $linha] = pedstoRequisicao((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id);

    $erro = ($this->enviar)(['purchases' => [['purchase_requisition_line_id' => $linha, 'quantity' => '4']]]);

    pedstoAssert422($this, $erro, 'Linha de requisição inválida');
    $this->assertSame(0.0, (float) DB::table('purchase_lines')->where('id', $linha)->value('po_quantity_purchased'));
});

it('UC-PEDC-08 (contrato T0) purchase_line_id no pedido novo e recusado', function () {
    [, $linha] = pedstoRequisicao((int) $this->vitima->id, $this->locVitima, (int) $this->donoVitima->id);

    $erro = ($this->enviar)(['purchases' => [['purchase_line_id' => $linha, 'quantity' => '1']]]);

    pedstoAssert422($this, $erro, 'Linha de compra inválida');
    $this->assertSame(10.0, (float) DB::table('purchase_lines')->where('id', $linha)->value('quantity'));
});

it('UC-PEDC-08 (controle) requisicao e linha da PROPRIA empresa passam pelas travas', function () {
    [$req, $linha] = pedstoRequisicao(PEDSTO_TENANT, $this->loc, (int) $this->user->id);

    $erro = ($this->enviar)([
        'purchase_requisition_ids' => [$req],
        'purchases' => [['purchase_requisition_line_id' => $linha, 'quantity' => '4']],
    ]);

    // Sem esta perna, um 422 incondicional passaria nos três testes acima.
    // O request para depois das travas (validação ou checagem de assinatura); o que importa
    // é que não foi uma trava de posse.
    $this->assertFalse(
        $erro instanceof HttpException && $erro->getStatusCode() === 422,
        'Uma trava de posse recusou referência da própria empresa: ' . ($erro?->getMessage() ?? '')
    );
});
