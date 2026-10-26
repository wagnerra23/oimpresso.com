<?php
// @covers-us US-RB-057

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Listeners\MarcarFaturaPagaDaCobranca;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-RB-057 · cobrança paga pelo PaymentGateway → fatura da assinatura paga + InvoicePaid.
 * Tenant 98 × 99 (ADR 0358). NUNCA biz=4. ⚠️ SKIP em SQLite: leia assertions (LC-13).
 *
 * VALOR — números à mão, conferidos por dois caminhos (o retorno do `marcar()` e a linha em
 * `rb_invoices` + o valor que sai no InvoicePaid):
 *   fatura 150,00 · cobrança paga 15.000 centavos → paga, InvoicePaid com 150.0
 *   fatura 150,00 · cobrança paga 14.999 centavos → NÃO paga (falta 0,01)
 *   fatura 150,00 · cobrança paga 15.050 centavos (juros) → paga
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do PaymentGateway/RecurringBilling.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.marcar_fatura_paga_por_cobranca' => true]);

    $this->contato = fn (int $biz) => DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente RB-057 '.uniqid(), 'mobile' => '', 'contact_id' => 'RB57-'.uniqid(),
        'created_by' => (int) DB::table('business')->where('id', $biz)->value('owner_id'), 'created_at' => now(), 'updated_at' => now()]);
    $this->fatura = fn (int $biz, string $valor = '150.00') => DB::table('rb_invoices')->insertGetId(['business_id' => $biz,
        'contact_id' => ($this->contato)($biz), 'numero_documento' => 'RB57-'.uniqid(), 'valor' => $valor, 'status' => 'open',
        'vencimento' => '2031-03-20', 'created_at' => now(), 'updated_at' => now()]);
    $this->cobranca = fn (int $biz, int $faturaId, int $centavos, string $status = 'paga', string $origem = 'invoice') => DB::table('cobrancas')->insertGetId([
        'business_id' => $biz, 'tipo' => 'boleto', 'status' => $status, 'valor_centavos' => 15000,
        'valor_pago_centavos' => $status === 'paga' ? $centavos : null, 'vencimento' => '2031-03-20',
        'paga_em' => $status === 'paga' ? now() : null, 'contact_id' => 1, 'descricao' => 'Teste US-RB-057',
        'idempotency_key' => uniqid('rb57', true), 'origem_type' => $origem, 'origem_id' => $faturaId, 'forma_pagamento' => 'boleto',
        'created_at' => now(), 'updated_at' => now()]);
    $this->evento = fn (int $cobId, int $biz, int $centavos) => new CobrancaPaga($cobId, $biz, $centavos,
        new DateTimeImmutable('2031-03-21 10:00:00'), 'boleto', new DateTimeImmutable(), null, 'invoice', null);
    $this->fat = fn (int $id) => DB::table('rb_invoices')->where('id', $id)->first();
});

it('US-RB-057 · com a config desligada a fatura segue em aberto', function () {
    config(['recurringbilling.marcar_fatura_paga_por_cobranca' => false]);
    $f = ($this->fatura)($this->b98);
    $c = ($this->cobranca)($this->b98, $f, 15000);

    event(($this->evento)($c, $this->b98, 15000));

    expect(($this->fat)($f)->status)->toBe('open');
});

it('US-RB-057 · cobrança paga do valor da fatura marca paga, grava pago_em e dispara InvoicePaid uma vez', function () {
    Event::fake([InvoicePaid::class]);
    $f = ($this->fatura)($this->b98);
    $c = ($this->cobranca)($this->b98, $f, 15000);
    $l = app(MarcarFaturaPagaDaCobranca::class);

    expect($l->marcar(($this->evento)($c, $this->b98, 15000)))->toBe('paga');
    expect($l->marcar(($this->evento)($c, $this->b98, 15000)))->toBe('ja_paga'); // retorno repetido

    $linha = ($this->fat)($f);
    expect($linha->status)->toBe('paid')
        ->and(substr((string) $linha->pago_em, 0, 16))->toBe('2031-03-21 10:00');
    Event::assertDispatchedTimes(InvoicePaid::class, 1);
    Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $e) => $e->businessId === $this->b98
        && $e->invoiceRef === $linha->numero_documento && $e->valor === 150.0 && $e->paidAt === '2031-03-21');
});

it('US-RB-057 · pagamento um centavo menor não marca paga; com juros a mais marca', function () {
    Event::fake([InvoicePaid::class]);
    $menor = ($this->fatura)($this->b98);
    $maior = ($this->fatura)($this->b98);
    $l = app(MarcarFaturaPagaDaCobranca::class);

    expect($l->marcar(($this->evento)(($this->cobranca)($this->b98, $menor, 14999), $this->b98, 14999)))->toBe('valor_insuficiente');
    expect($l->marcar(($this->evento)(($this->cobranca)($this->b98, $maior, 15050), $this->b98, 15050)))->toBe('paga');

    expect(($this->fat)($menor)->status)->toBe('open')
        ->and(($this->fat)($maior)->status)->toBe('paid');
    Event::assertDispatchedTimes(InvoicePaid::class, 1);
});

it('US-RB-057 · cobrança não paga, ou de outra origem, é ignorada', function () {
    $f = ($this->fatura)($this->b98);
    $l = app(MarcarFaturaPagaDaCobranca::class);

    expect($l->marcar(($this->evento)(($this->cobranca)($this->b98, $f, 15000, 'emitida'), $this->b98, 15000)))->toBe('ignorado')
        ->and($l->marcar(($this->evento)(($this->cobranca)($this->b98, $f, 15000, 'paga', 'sale'), $this->b98, 15000)))->toBe('ignorado')
        ->and(($this->fat)($f)->status)->toBe('open');
});

it('US-RB-057 · Tier 0: cobrança da 99 apontando para fatura da 98 não paga nada', function () {
    $f98 = ($this->fatura)($this->b98);
    $c99 = ($this->cobranca)($this->b99, $f98, 15000);
    $l = app(MarcarFaturaPagaDaCobranca::class);

    // Evento da 99 lê a fatura no business 99: a da 98 não existe lá.
    expect($l->marcar(($this->evento)($c99, $this->b99, 15000)))->toBe('ignorado');
    // Evento da 98 com a cobrança da 99: a cobrança não existe na 98.
    expect($l->marcar(($this->evento)($c99, $this->b98, 15000)))->toBe('ignorado');
    expect(($this->fat)($f98)->status)->toBe('open');
});
