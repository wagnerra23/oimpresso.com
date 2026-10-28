<?php
// @covers-us US-RB-011

declare(strict_types=1);
// Cobre UC-RBCC-01..04 (resources/js/Pages/RecurringBilling/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\RecurringBilling\Listeners\SalvarCartaoDaCobrancaPaga;
use Modules\RecurringBilling\Models\CartaoSalvo;
use Modules\RecurringBilling\Services\CartaoSalvoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-RB-011 · fatura paga no cartão (página hospedada do Asaas) → token do cartão salvo e ligado à
 * assinatura. Só token, últimos 4 e bandeira: nenhum número de cartão entra no banco nem aqui.
 *
 * Tenant 98 contra o 99 (ADR 0358), nunca o 4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling/PaymentGateway (MySQL).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    config(['recurringbilling.salvar_cartao' => true]);
});

/** Assinatura + fatura paga no cartão + webhook do Asaas com o `creditCard` dado. Devolve [sub, cobranca]. */
function ccFaturaPagaNoCartao(int $biz, array $creditCard, string $forma = 'cartao'): array
{
    $owner = (int) DB::table('business')->where('id', $biz)->value('owner_id');
    $contato = DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer',
        'name' => 'Cliente CC '.uniqid(), 'mobile' => '', 'contact_id' => 'CC-'.uniqid(),
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now()]);
    $sub = DB::table('rb_subscriptions')->insertGetId(['business_id' => $biz, 'plan_id' => null,
        'contact_id' => $contato, 'status' => 'active', 'start_date' => '2031-03-01', 'next_due_date' => '2031-04-01',
        'billing_anchor_date' => '2031-04-01', 'payment_method' => 'card', 'metadata' => json_encode(['valor' => '89.90']),
        'created_at' => now(), 'updated_at' => now()]);
    $fatura = DB::table('rb_invoices')->insertGetId(['business_id' => $biz, 'subscription_id' => $sub,
        'contact_id' => $contato, 'numero_documento' => 'CC-'.uniqid(), 'valor' => '89.90', 'status' => 'open',
        'vencimento' => '2031-03-01', 'created_at' => now(), 'updated_at' => now()]);
    $cob = DB::table('cobrancas')->insertGetId(['business_id' => $biz, 'tipo' => 'card', 'status' => 'paga',
        'valor_centavos' => 8990, 'valor_pago_centavos' => 8990, 'vencimento' => '2031-03-01', 'paga_em' => now(),
        'contact_id' => $contato, 'descricao' => 'Teste US-RB-011', 'idempotency_key' => uniqid('cc', true),
        'origem_type' => 'invoice', 'origem_id' => $fatura, 'forma_pagamento' => $forma,
        'gateway_external_id' => 'pay_'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('gateway_webhook_events')->insert(['business_id' => $biz, 'gateway_key' => 'asaas',
        'evento' => 'PAYMENT_CONFIRMED', 'gateway_event_id' => 'evt_'.uniqid(), 'cobranca_id' => $cob,
        'payload' => json_encode(['event' => 'PAYMENT_CONFIRMED', 'payment' => ['billingType' => 'CREDIT_CARD',
            'creditCard' => $creditCard]]),
        'signature_valid' => true, 'created_at' => now(), 'updated_at' => now()]);

    return [$sub, $cob];
}

function ccEvento(int $cob, int $biz, string $forma = 'cartao'): CobrancaPaga
{
    return new CobrancaPaga($cob, $biz, 8990, new DateTimeImmutable('2031-03-01 10:00:00'), $forma,
        new DateTimeImmutable(), null, 'invoice', null);
}

it('UC-RBCC-01 · fatura paga no cartão salva o token cifrado com últimos 4 e bandeira, e liga à assinatura', function () {
    [$sub, $cob] = ccFaturaPagaNoCartao($this->b98, ['creditCardToken' => 'tok-teste-a1', 'creditCardNumber' => '8829', 'creditCardBrand' => 'MASTERCARD']);

    event(ccEvento($cob, $this->b98)); // pelo evento: prova que o listener está registrado

    $linha = DB::table('rb_cartoes_salvos')->where('business_id', $this->b98)->where('origem_cobranca_id', $cob)->first();
    expect($linha)->not->toBeNull()
        ->and($linha->ultimos_4)->toBe('8829')
        ->and($linha->bandeira)->toBe('MASTERCARD')
        ->and($linha->gateway_key)->toBe('asaas')
        ->and($linha->token)->not->toBe('tok-teste-a1'); // no banco o token está cifrado
    // SUPERADMIN: teste lê o cartão do tenant 98 sem sessão.
    $modelo = CartaoSalvo::withoutGlobalScopes()->find($linha->id);
    expect($modelo->token)->toBe('tok-teste-a1')
        ->and($modelo->toArray())->not->toHaveKey('token');
    expect((int) DB::table('rb_subscriptions')->where('id', $sub)->value('cartao_salvo_id'))->toBe((int) $linha->id);
});

it('UC-RBCC-02 · o mesmo cartão não é salvo duas vezes e a assinatura que já tem cartão não troca', function () {
    [$sub, $cob] = ccFaturaPagaNoCartao($this->b98, ['creditCardToken' => 'tok-teste-b2', 'creditCardNumber' => '8829', 'creditCardBrand' => 'MASTERCARD']);
    $l = app(SalvarCartaoDaCobrancaPaga::class);

    expect($l->salvar(ccEvento($cob, $this->b98)))->toBe('salvo')
        ->and($l->salvar(ccEvento($cob, $this->b98)))->toBe('ja_salvo');
    $primeiro = (int) DB::table('rb_subscriptions')->where('id', $sub)->value('cartao_salvo_id');

    $outro = app(CartaoSalvoService::class)->registrar($this->b98, 1, $sub, 'asaas', ['token' => 'tok-teste-c3', 'ultimos_4' => '1111']);
    expect($outro)->toBe('salvo')
        ->and(DB::table('rb_cartoes_salvos')->where('business_id', $this->b98)->where('origem_cobranca_id', $cob)->count())->toBe(1)
        ->and((int) DB::table('rb_subscriptions')->where('id', $sub)->value('cartao_salvo_id'))->toBe($primeiro);
});

it('UC-RBCC-03 · campo com sequência de número de cartão é recusado e nada é gravado; boleto e flag desligada não salvam', function () {
    $antes = DB::table('rb_cartoes_salvos')->where('business_id', $this->b98)->count();
    $sequencia = str_repeat('1', 16); // 16 dígitos sintéticos: o guarda recusa qualquer sequência 13..19

    [, $cob] = ccFaturaPagaNoCartao($this->b98, ['creditCardToken' => 'tok-teste-d4', 'creditCardNumber' => $sequencia]);
    expect(app(SalvarCartaoDaCobrancaPaga::class)->salvar(ccEvento($cob, $this->b98)))->toBe('recusado_numero');

    [, $cobBoleto] = ccFaturaPagaNoCartao($this->b98, ['creditCardToken' => 'tok-teste-e5'], 'boleto');
    expect(app(SalvarCartaoDaCobrancaPaga::class)->salvar(ccEvento($cobBoleto, $this->b98, 'boleto')))->toBe('ignorado');

    config(['recurringbilling.salvar_cartao' => false]);
    [, $cobOff] = ccFaturaPagaNoCartao($this->b98, ['creditCardToken' => 'tok-teste-f6', 'creditCardNumber' => '4242']);
    event(ccEvento($cobOff, $this->b98));

    expect(DB::table('rb_cartoes_salvos')->where('business_id', $this->b98)->count())->toBe($antes);
});

it('UC-RBCC-04 · evento com business errado não salva o cartão da cobrança do outro', function () {
    [$sub, $cob] = ccFaturaPagaNoCartao($this->b99, ['creditCardToken' => 'tok-teste-g7', 'creditCardNumber' => '8829']);

    expect(app(SalvarCartaoDaCobrancaPaga::class)->salvar(ccEvento($cob, $this->b98)))->toBe('ignorado');
    expect(DB::table('rb_cartoes_salvos')->where('origem_cobranca_id', $cob)->count())->toBe(0)
        ->and(DB::table('rb_subscriptions')->where('id', $sub)->value('cartao_salvo_id'))->toBeNull();
});
