<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de VALOR do pagamento lançado junto com a Nova compra React (`payment[]` do
 * `PurchaseController@store` → `TransactionUtil::createOrUpdatePaymentLines`).
 *
 * UC-PURCRE-16 `[V0]` — o pago na entrada vira 1 linha de pagamento e move o status da compra.
 * UC-PURCRE-17 `[V0]` `[T0]` — pagamento acima do total, conta de outra empresa e forma
 * inexistente são recusados antes de gravar a compra.
 *
 * POR QUE EXISTE: até 2026-10-27 só a Blade lançava pagamento na criação. Junto veio um
 * conserto: o createOrUpdatePaymentLines usava `uf_date` (formato da empresa) e a data ISO
 * que o React manda dava "Trailing data" → "algo deu errado". Agora usa `uf_datetime_input`.
 *
 * DUPLA PROVA: (a) a linha em `transaction_payments` e o `payment_status` gravado;
 * (b) o saldo refeito à mão (final_total − soma das linhas) contra o status: 25,00 em aberto
 * tem de ser `partial`, 0,00 tem de ser `paid`.
 *
 * Tenant 98 (ADR 0358). Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PURCRE-16
 * @covers-uc UC-PURCRE-17
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS (MySQL) ausente — roda na lane purchase-pest / CT 100.');
    }
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $this->biz = EstoqueFixture::businessId();
    $role = Role::firstOrCreate(['name' => 'Admin#' . $this->biz, 'guard_name' => 'web'], ['business_id' => $this->biz]);
    $this->user = App\User::factory()->create([
        'business_id' => $this->biz,
        'username' => 'pcp_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->location = EstoqueFixture::locationId($this->biz, '-PCP');
    $this->produto = EstoqueFixture::singleProduct($this->biz);
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => $this->biz,
        'type' => 'supplier',
        'name' => 'Fornecedor PCP',
        'contact_id' => 'CT-PCP-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

/** 4 × 10,00 = 40,00, no formato da tela React. */
function pcpPayload($t, array $pagamento): array
{
    return [
        'ref_no' => 'PCP-' . uniqid(),
        'status' => 'received',
        'contact_id' => $t->contactId,
        'transaction_date' => '2026-10-27 10:00',
        'location_id' => $t->location,
        'exchange_rate' => '1',
        'total_before_tax' => '40',
        'discount_type' => 'fixed',
        'discount_amount' => '0',
        'tax_amount' => '0',
        'shipping_charges' => '0',
        'final_total' => '40',
        'purchases' => [[
            'product_id' => $t->produto->productId,
            'variation_id' => $t->produto->variationId(),
            'product_unit_id' => $t->produto->unitId,
            'quantity' => '4',
            'pp_without_discount' => '10',
            'discount_percent' => '0',
            'purchase_price' => '10',
            'purchase_price_inc_tax' => '10',
            'item_tax' => '0',
            'purchase_line_tax_id' => '',
        ]],
        'payment' => $pagamento,
    ];
}

function pcpCompra(int $biz, string $ref): ?Transaction
{
    return Transaction::where('business_id', $biz)->where('type', 'purchase')->where('ref_no', $ref)->first();
}

it('UC-PURCRE-16 [V0] pagar 15,00 na entrada de uma compra de 40,00 deixa 25,00 em aberto, pelos dois caminhos', function () {
    $dados = pcpPayload($this, [['amount' => '15', 'method' => 'cash', 'paid_on' => '2026-10-27 10:30', 'account_id' => '', 'note' => '']]);
    $resp = $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcpCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull('Compra não criada. HTTP=' . $resp->status() . ' flash=' . json_encode(session('status')));

    // (a) gravado
    $linhas = DB::table('transaction_payments')->where('transaction_id', $tx->id)->get();
    expect($linhas)->toHaveCount(1);
    expect(round((float) $linhas[0]->amount, 2))->toBe(15.0);
    expect($linhas[0]->method)->toBe('cash');
    expect(substr((string) $linhas[0]->paid_on, 0, 16))->toBe('2026-10-27 10:30');
    expect($tx->payment_status)->toBe('partial');

    // (b) saldo refeito à mão
    expect(round((float) $tx->final_total - (float) $linhas->sum('amount'), 2))->toBe(25.0);
});

it('UC-PURCRE-16 [V0] pagar o total na entrada deixa a compra paga e o saldo zero', function () {
    $dados = pcpPayload($this, [['amount' => '40', 'method' => 'cash', 'paid_on' => '2026-10-27 10:30', 'account_id' => '', 'note' => '']]);
    $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcpCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull();
    expect($tx->payment_status)->toBe('paid');
    $soma = (float) DB::table('transaction_payments')->where('transaction_id', $tx->id)->sum('amount');
    expect(round((float) $tx->final_total - $soma, 2))->toBe(0.0);
});

it('UC-PURCRE-16 sem pagamento a compra nasce a prazo e sem linhas', function () {
    $dados = pcpPayload($this, []);
    $this->actingAs($this->user)->post('/purchases', $dados);

    $tx = pcpCompra($this->biz, $dados['ref_no']);
    expect($tx)->not->toBeNull();
    expect($tx->payment_status)->toBe('due');
    expect(DB::table('transaction_payments')->where('transaction_id', $tx->id)->count())->toBe(0);
});

it('UC-PURCRE-17 [V0] pagamento de 40,01 numa compra de 40,00 é recusado e a compra não é criada', function () {
    $dados = pcpPayload($this, [['amount' => '40,01', 'method' => 'cash', 'paid_on' => '2026-10-27 10:30', 'account_id' => '', 'note' => '']]);
    $this->actingAs($this->user)->post('/purchases', $dados)->assertSessionHasErrors('payment');

    expect(pcpCompra($this->biz, $dados['ref_no']))->toBeNull();
});

it('UC-PURCRE-17 [T0] conta de outra empresa é recusada e a compra não é criada', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $contaAlheia = DB::table('accounts')->insertGetId([
        'business_id' => $outra,
        'name' => 'Conta alheia PCP',
        'account_number' => 'PCP-' . uniqid(),
        'created_by' => $this->user->id,
        'is_closed' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $dados = pcpPayload($this, [['amount' => '10', 'method' => 'cash', 'paid_on' => '2026-10-27 10:30', 'account_id' => (string) $contaAlheia, 'note' => '']]);
    $this->actingAs($this->user)->post('/purchases', $dados)->assertSessionHasErrors('payment.0.account_id');

    expect(pcpCompra($this->biz, $dados['ref_no']))->toBeNull();
    expect(DB::table('transaction_payments')->where('account_id', $contaAlheia)->count())->toBe(0);
});

it('UC-PURCRE-17 forma de pagamento inexistente é recusada', function () {
    $dados = pcpPayload($this, [['amount' => '10', 'method' => 'xpto', 'paid_on' => '2026-10-27 10:30', 'account_id' => '', 'note' => '']]);
    $this->actingAs($this->user)->post('/purchases', $dados)->assertSessionHasErrors('payment.0.method');

    expect(pcpCompra($this->biz, $dados['ref_no']))->toBeNull();
});
