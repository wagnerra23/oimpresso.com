<?php
// @covers-us US-CRM-093

declare(strict_types=1);

use App\Events\SellCreatedOrModified;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Services\Fidelidade\CashbackService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-CASH-01..07 · US-CRM-093 — a venda paga gera crédito de cashback no saldo de Adiantamento
 * do cliente, conforme a regra da empresa. Âncora: SPEC Crm US-CRM-093 (critérios 1-7).
 *
 * Números feitos à mão (regra 5%, mínimo 50,00, validade 90 dias; cliente começa com saldo 20,00):
 *   venda paga 200,00                       → base 200,00 → crédito 10,00 → saldo 30,00
 *   venda 200,00 com 50,00 em Adiantamento  → base 150,00 → crédito  7,50 → saldo 27,50
 *   venda paga 40,00                        → abaixo do mínimo → nada, saldo 20,00
 *   venda 33,33 com regra 7,5% e mínimo 0   → 2,49975 → ROUND 2 casas → 2,50
 *
 * Tenant 98 (fictício, ADR 0358); 99 é a outra empresa. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    foreach (['transactions', 'transaction_payments', 'contacts', 'crm_cashback_regras', 'crm_cashback_creditos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente (roda na lane MySQL).");
        }
    }
    config(['crm.cashback.enabled' => true]);
    $this->biz = (int) $this->seededTenant()->id;
    $this->outro = (int) $this->seededSupportClientTenant()->id;
    $this->uid = (int) $this->usuarioComPermissoes([], $this->seededTenant())->id;

    $this->regra = fn (int $biz, string $pct = '5.00', string $min = '50.0000') => DB::table('crm_cashback_regras')
        ->updateOrInsert(['business_id' => $biz], ['percentual' => $pct, 'valor_minimo' => $min, 'validade_dias' => 90, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->cliente = fn (int $biz, bool $padrao = false) => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente Cashback Teste', 'contact_id' => 'CB-' . uniqid(),
        'balance' => '20.0000', 'is_default' => $padrao ? 1 : 0, 'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->venda = function (int $biz, int $cliente, string $total, string $status = 'paid', string $adiantamento = '0') {
        $id = (int) DB::table('transactions')->insertGetId([
            'business_id' => $biz, 'type' => 'sell', 'status' => 'final', 'payment_status' => $status, 'contact_id' => $cliente,
            'invoice_no' => 'CB-' . uniqid(), 'transaction_date' => now(), 'final_total' => $total, 'total_before_tax' => $total,
            'tax_amount' => 0, 'discount_amount' => 0, 'created_by' => $this->uid, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($adiantamento !== '0') {
            DB::table('transaction_payments')->insert([
                'business_id' => $biz, 'transaction_id' => $id, 'amount' => $adiantamento, 'method' => 'advance', 'is_return' => 0,
                'paid_on' => now(), 'created_by' => $this->uid, 'payment_for' => $cliente, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    };
    $this->saldo = fn (int $cliente) => (string) DB::table('contacts')->where('id', $cliente)->value('balance');
    $this->servico = fn () => app(CashbackService::class);
});

it('UC-CASH-01 venda paga gera crédito pela regra e sobe o saldo do cliente', function () {
    ($this->regra)($this->biz);
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '200.00');

    $r = ($this->servico)()->concederParaVenda($this->biz, $v);

    expect($r['valor'])->toBe('10.0000');
    expect($r['expira_em'])->toBe(now()->addDays(90)->toDateString());
    expect(($this->saldo)($c))->toBe('30.0000');
    $credito = DB::table('crm_cashback_creditos')->where('business_id', $this->biz)->where('transaction_id', $v)->first();
    expect((string) $credito->base)->toBe('200.0000');
    expect($credito->situacao)->toBe('ativo');
});

it('UC-CASH-02 o que foi pago com Adiantamento não rende cashback', function () {
    ($this->regra)($this->biz);
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '200.00', 'paid', '50.00');

    expect(($this->servico)()->concederParaVenda($this->biz, $v)['valor'])->toBe('7.5000');
    expect(($this->saldo)($c))->toBe('27.5000');
});

it('UC-CASH-03 abaixo do mínimo, venda não paga e Cliente padrão não geram crédito', function () {
    ($this->regra)($this->biz);
    $c = ($this->cliente)($this->biz);
    $padrao = ($this->cliente)($this->biz, true);

    expect(($this->servico)()->concederParaVenda($this->biz, ($this->venda)($this->biz, $c, '40.00')))->toBeNull();
    $aPrazo = ($this->venda)($this->biz, $c, '200.00', 'due');
    expect(($this->servico)()->concederParaVenda($this->biz, $aPrazo))->toBeNull();
    expect(($this->servico)()->concederParaVenda($this->biz, ($this->venda)($this->biz, $padrao, '200.00')))->toBeNull();
    expect(($this->saldo)($c))->toBe('20.0000');

    // a prazo quitada depois: aí credita
    DB::table('transactions')->where('id', $aPrazo)->update(['payment_status' => 'paid']);
    expect(($this->servico)()->concederParaVenda($this->biz, $aPrazo)['valor'])->toBe('10.0000');
});

it('UC-CASH-04 cada venda credita uma vez só', function () {
    ($this->regra)($this->biz);
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '200.00');

    ($this->servico)()->concederParaVenda($this->biz, $v);
    expect(($this->servico)()->concederParaVenda($this->biz, $v))->toBeNull();
    expect(DB::table('crm_cashback_creditos')->where('transaction_id', $v)->count())->toBe(1);
    expect(($this->saldo)($c))->toBe('30.0000');
});

it('UC-CASH-05 com a flag desligada ou sem regra ativa, nada acontece', function () {
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '200.00');
    expect(($this->servico)()->concederParaVenda($this->biz, $v))->toBeNull(); // sem regra

    ($this->regra)($this->biz);
    config(['crm.cashback.enabled' => false]);
    expect(($this->servico)()->concederParaVenda($this->biz, $v))->toBeNull();
    expect(($this->saldo)($c))->toBe('20.0000');
});

it('UC-CASH-06 a regra e a venda são da empresa: outra empresa não credita', function () {
    ($this->regra)($this->outro);
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '200.00');

    expect(($this->servico)()->concederParaVenda($this->biz, $v))->toBeNull(); // regra é do 99
    expect(($this->servico)()->concederParaVenda($this->outro, $v))->toBeNull(); // venda é do 98
    expect(DB::table('crm_cashback_creditos')->where('transaction_id', $v)->count())->toBe(0);
    expect(($this->saldo)($c))->toBe('20.0000');
});

it('UC-CASH-07 a gravação da venda dispara o crédito, arredondado em 2 casas', function () {
    ($this->regra)($this->biz, '7.50', '0');
    $c = ($this->cliente)($this->biz);
    $v = ($this->venda)($this->biz, $c, '33.33');

    SellCreatedOrModified::dispatch(Transaction::findOrFail($v));

    expect((string) DB::table('crm_cashback_creditos')->where('transaction_id', $v)->value('valor'))->toBe('2.5000');
    expect(($this->saldo)($c))->toBe('22.5000');
});
