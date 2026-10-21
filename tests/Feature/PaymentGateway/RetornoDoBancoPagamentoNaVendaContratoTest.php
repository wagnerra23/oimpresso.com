<?php
// @covers-us US-PCP-025 (UC-PCLIB-08: o retorno do banco grava o pagamento da venda)

declare(strict_types=1);

use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Listeners\LancarPagamentoDaCobrancaNaVenda;
use Modules\Producao\Services\SinalDaOrdem;

uses(DatabaseTransactions::class);

/**
 * UC-PCLIB-08 · retorno do banco → pagamento na venda (US-PCP-025 · UC-FLX-28).
 * Âncora: Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md.
 * Tenant 98 × 99 (ADR 0358). NUNCA biz=4. ⚠️ SKIP em SQLite: leia assertions (LC-13).
 *
 * VALOR — números feitos à mão e conferidos por dois caminhos (a linha gravada e a conta do
 * SinalDaOrdem, que soma transaction_payments em DECIMAL):
 *   venda 1.000,00 · cobrança paga 30.000 centavos → pagamento 300,00 · status partial · sinal 30% ok
 *   venda 1.000,00 · cobrança paga 100.000 centavos → pagamento 1.000,00 · status paid
 *   venda 1.000,00 já com 800,00 pago à mão · cobrança 30.000 → não lança (300,00 > saldo 200,00)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['sell.view'], $this->seededTenant());
    config(['paymentgateway.lancar_pagamento_na_venda' => true, 'app.saas_owner_business_id' => 1]);

    $this->venda = fn (int $biz, string $total) => DB::table('transactions')->insertGetId(['business_id' => $biz, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'contact_id' => 1, 'invoice_no' => 'PGV-'.uniqid(), 'transaction_date' => now(),
        'total_before_tax' => $total, 'final_total' => $total, 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->cobranca = fn (int $biz, int $origemId, int $centavos, string $status = 'paga', string $origem = 'sale') => DB::table('cobrancas')->insertGetId([
        'business_id' => $biz, 'tipo' => 'boleto', 'status' => $status, 'valor_centavos' => $centavos,
        'valor_pago_centavos' => $status === 'paga' ? $centavos : null, 'vencimento' => now()->toDateString(),
        'paga_em' => $status === 'paga' ? now() : null, 'contact_id' => 1, 'descricao' => 'Teste UC-PCLIB-08',
        'idempotency_key' => uniqid('pgv', true), 'origem_type' => $origem, 'origem_id' => $origemId, 'forma_pagamento' => 'boleto',
        'created_at' => now(), 'updated_at' => now()]);
    $this->evento = fn (int $cobId, int $biz, int $centavos) => new CobrancaPaga($cobId, $biz, $centavos, new DateTimeImmutable(),
        'boleto', new DateTimeImmutable(), null, 'sale', null);
    $this->pagamentos = fn (int $tx) => TransactionPayment::query()->withoutGlobalScopes()->where('transaction_id', $tx)->get();
});

it('UC-PCLIB-08 · com a config desligada o retorno não grava nada na venda', function () {
    config(['paymentgateway.lancar_pagamento_na_venda' => false]);
    $tx = ($this->venda)($this->b98, '1000.00');
    $cob = ($this->cobranca)($this->b98, $tx, 30000);

    event(($this->evento)($cob, $this->b98, 30000));

    expect(($this->pagamentos)($tx))->toHaveCount(0);
});

it('UC-PCLIB-08 · cobrança paga de 300,00 vira um pagamento de 300,00 na venda, uma vez só', function () {
    $tx = ($this->venda)($this->b98, '1000.00');
    $cob = ($this->cobranca)($this->b98, $tx, 30000);

    event(($this->evento)($cob, $this->b98, 30000));
    // Retorno repetido (webhook e CNAB do mesmo pagamento) não duplica.
    expect(app(LancarPagamentoDaCobrancaNaVenda::class)->lancar(($this->evento)($cob, $this->b98, 30000)))->toBe('ja_lancado');

    $p = ($this->pagamentos)($tx);
    expect($p)->toHaveCount(1)
        ->and(number_format((float) $p[0]->amount, 2, '.', ''))->toBe('300.00')
        ->and($p[0]->transaction_no)->toBe('PG-COB-'.$cob)
        ->and((int) $p[0]->business_id)->toBe($this->b98)
        ->and($p[0]->method)->toBe('bank_transfer');
    // Caminho 2: a conta do sinal (SUM em DECIMAL no MySQL) enxerga os mesmos 300,00.
    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00'))->toBe(['pago' => '300.00', 'exigido' => '300.00', 'ok' => true]);
    expect(DB::table('transactions')->where('id', $tx)->value('payment_status'))->toBe('partial');
});

it('UC-PCLIB-08 · cobrança paga do total quita a venda', function () {
    $tx = ($this->venda)($this->b98, '1000.00');
    $cob = ($this->cobranca)($this->b98, $tx, 100000);

    event(($this->evento)($cob, $this->b98, 100000));

    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '100.00')['pago'])->toBe('1000.00')
        ->and(DB::table('transactions')->where('id', $tx)->value('payment_status'))->toBe('paid');
});

it('UC-PCLIB-08 · valor maior que o saldo em aberto não é lançado (já registrado à mão)', function () {
    $tx = ($this->venda)($this->b98, '1000.00');
    DB::table('transaction_payments')->insert(['business_id' => $this->b98, 'transaction_id' => $tx, 'amount' => '800.00', 'is_return' => 0,
        'method' => 'cash', 'paid_on' => now(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
    $cob = ($this->cobranca)($this->b98, $tx, 30000);

    expect(app(LancarPagamentoDaCobrancaNaVenda::class)->lancar(($this->evento)($cob, $this->b98, 30000)))->toBe('sem_saldo');
    expect(($this->pagamentos)($tx))->toHaveCount(1);
    expect(app(SinalDaOrdem::class)->situacao($this->b98, $tx, '30.00')['pago'])->toBe('800.00');
});

it('UC-PCLIB-08 · cobrança não paga, ou que não é de venda, é ignorada', function () {
    $tx = ($this->venda)($this->b98, '1000.00');
    $emitida = ($this->cobranca)($this->b98, $tx, 30000, 'emitida');
    $avulsa = ($this->cobranca)($this->b98, $tx, 30000, 'paga', 'avulsa');
    $l = app(LancarPagamentoDaCobrancaNaVenda::class);

    expect($l->lancar(($this->evento)($emitida, $this->b98, 30000)))->toBe('ignorado')
        ->and($l->lancar(($this->evento)($avulsa, $this->b98, 30000)))->toBe('ignorado')
        ->and(($this->pagamentos)($tx))->toHaveCount(0);
});

it('UC-PCLIB-08 · Tier 0: cobrança de outra empresa nunca paga a venda da 98', function () {
    $tx = ($this->venda)($this->b98, '1000.00');
    // Cobrança da 99 apontando a venda da 98.
    $cob99 = ($this->cobranca)($this->b99, $tx, 30000);
    // Cobrança da 98 com o evento chegando como se fosse da 99.
    $cob98 = ($this->cobranca)($this->b98, $tx, 30000);
    $l = app(LancarPagamentoDaCobrancaNaVenda::class);

    expect($l->lancar(($this->evento)($cob99, $this->b99, 30000)))->toBe('ignorado')
        ->and($l->lancar(($this->evento)($cob98, $this->b99, 30000)))->toBe('ignorado')
        ->and(($this->pagamentos)($tx))->toHaveCount(0);
    // Controle positivo: a mesma cobrança, com a empresa certa, lança.
    expect($l->lancar(($this->evento)($cob98, $this->b98, 30000)))->toBe('lancado');
});

it('UC-PCLIB-08 · o business dono do SaaS fica de fora (lá o Financeiro já cria o título quitado)', function () {
    config(['app.saas_owner_business_id' => $this->b98]);
    $tx = ($this->venda)($this->b98, '1000.00');
    $cob = ($this->cobranca)($this->b98, $tx, 30000);

    // Direto no listener: o event() acionaria também o do Financeiro, que escreve no dono do SaaS.
    app(LancarPagamentoDaCobrancaNaVenda::class)->handle(($this->evento)($cob, $this->b98, 30000));

    expect(($this->pagamentos)($tx))->toHaveCount(0);
});
