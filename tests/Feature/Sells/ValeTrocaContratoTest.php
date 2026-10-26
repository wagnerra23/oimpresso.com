<?php
// @covers-us US-VEST-021

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-SRET-VT-01..05 · US-VEST-021 — vale-troca: o valor da devolução vira saldo de Adiantamento
 * do cliente (decisão [W] 2026-10-26), o saldo que o caixa já aceita como forma de pagamento.
 *
 * Âncora: resources/js/Pages/SellReturn/Index.casos.md (UC-SRET-VT-*), derivado da US-VEST-021.
 *
 * Números feitos à mão (cliente começa com saldo 20,00):
 *   devolução de 150,00 → vale de 150,00 → saldo 170,00, devolução paga
 *   apagar o vale → saldo volta a 20,00 (antes do conserto do listener: 320,00)
 *   devolução de 150,00 com 50,00 já pagos em dinheiro → vale de 100,00 → saldo 120,00
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! Schema::hasTable('transaction_payments') || ! Schema::hasColumn('contacts', 'balance')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    $biz = $this->seededTenant();
    $this->bizId = (int) $biz->id;
    $this->user = $this->usuarioComPermissoes(['access_sell_return'], $biz);

    $this->cliente = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'customer', 'name' => 'Cliente Vale Teste',
        'contact_id' => 'VT-' . uniqid(), 'balance' => '20.0000', 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->devolucao = fn (int $bizId = 0, ?int $cliente = null) => Transaction::create([
        'business_id' => $bizId ?: $this->bizId, 'type' => 'sell_return', 'status' => 'final',
        'payment_status' => 'due', 'contact_id' => $cliente ?? $this->cliente,
        'invoice_no' => 'DEV-' . uniqid(), 'transaction_date' => now(), 'final_total' => 150.00,
        'total_before_tax' => 150.00, 'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => $this->user->id,
    ]);
    $this->saldo = fn () => (string) DB::table('contacts')->where('id', $this->cliente)->value('balance');
    $this->vale = fn (int $id) => $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->bizId, 'business.id' => $this->bizId])
        ->postJson("/sell-return/{$id}/vale-troca");
});

it('UC-SRET-VT-01 a devolução vira saldo do cliente e fica paga', function () {
    $dev = ($this->devolucao)();

    ($this->vale)($dev->id)->assertOk()
        ->assertJsonPath('valor', '150.0000')
        ->assertJsonPath('saldo_cliente', '170.0000');

    expect(($this->saldo)())->toBe('170.0000');
    expect(DB::table('transactions')->where('id', $dev->id)->value('payment_status'))->toBe('paid');
    $tp = DB::table('transaction_payments')->where('transaction_id', $dev->id)->first();
    expect([$tp->method, $tp->transaction_no, (int) $tp->payment_for])->toBe(['advance', 'VT-' . $dev->id, $this->cliente]);
});

it('UC-SRET-VT-02 credita só o que falta pagar da devolução', function () {
    $dev = ($this->devolucao)();
    DB::table('transaction_payments')->insert([
        'business_id' => $this->bizId, 'transaction_id' => $dev->id, 'amount' => '50.0000', 'is_return' => 0,
        'method' => 'cash', 'paid_on' => now(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    ($this->vale)($dev->id)->assertOk()->assertJsonPath('valor', '100.0000');
    expect(($this->saldo)())->toBe('120.0000');
});

it('UC-SRET-VT-03 devolução já paga não vira vale de novo', function () {
    $dev = ($this->devolucao)();
    ($this->vale)($dev->id)->assertOk();

    ($this->vale)($dev->id)->assertStatus(422)->assertJsonPath('erro', 'sem_saldo');
    expect(($this->saldo)())->toBe('170.0000');
});

it('UC-SRET-VT-04 apagar o vale tira do saldo o que ele pôs', function () {
    $dev = ($this->devolucao)();
    ($this->vale)($dev->id)->assertOk();
    $tp = TransactionPayment::where('transaction_id', $dev->id)->firstOrFail();

    TransactionPayment::deletePayment($tp);

    expect(($this->saldo)())->toBe('20.0000');
});

it('UC-SRET-VT-05 [T0] devolução de outra empresa não vira vale', function () {
    $outro = (int) DB::table('business')->where('id', '!=', $this->bizId)->orderBy('id')->value('id');
    if ($outro === 0) {
        $this->markTestSkipped('Só um business semeado.');
    }
    $alheio = (int) DB::table('contacts')->insertGetId([
        'business_id' => $outro, 'type' => 'customer', 'name' => 'Cliente alheio',
        'contact_id' => 'VTX-' . uniqid(), 'balance' => '0.0000', 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $dev = ($this->devolucao)($outro, $alheio);

    ($this->vale)($dev->id)->assertStatus(404);
    expect(DB::table('transaction_payments')->where('transaction_id', $dev->id)->count())->toBe(0);
    expect((string) DB::table('contacts')->where('id', $alheio)->value('balance'))->toBe('0.0000');
});
