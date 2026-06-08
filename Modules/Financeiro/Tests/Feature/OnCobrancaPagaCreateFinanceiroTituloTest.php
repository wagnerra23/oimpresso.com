<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Models\Titulo;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;

uses(Tests\TestCase::class);

/**
 * Pest — ADR 0170 Onda 5 SIMPLIFICADA.
 *
 * Listener Financeiro escuta CobrancaPaga e cria Titulo a receber em biz=1
 * (Wagner contabiliza receita SaaS). Cobranças de outros businesses são
 * ignoradas (escopo conservador Onda 5).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Financeiro + PaymentGateway.');
    }
    if (!Schema::hasTable('fin_titulos') || !Schema::hasTable('cobrancas')) {
        $this->markTestSkipped('Schema Financeiro + PaymentGateway ausente.');
    }
});

function onda5fin_makeCobranca(int $businessId, int $valorCentavos): Cobranca
{
    return Cobranca::create([
        'business_id'                   => $businessId,
        'payment_gateway_credential_id' => null,
        'gateway_external_id'           => 'TEST-' . uniqid(),
        'tipo'                          => 'pix_recv',
        'status'                        => 'paga',
        'valor_centavos'                => $valorCentavos,
        'valor_pago_centavos'           => $valorCentavos,
        'vencimento'                    => now()->toDateString(),
        'paga_em'                       => now(),
        'contact_id'                    => null,
        'payer_cpf_cnpj'                => null,
        'payer_name'                    => 'Tenant Test',
        'payer_email'                   => null,
        'descricao'                     => 'Test cobranca onda 5',
        'idempotency_key'               => 'test-onda5fin-' . uniqid(),
        'origem_type'                   => 'subscription_license',
        'origem_id'                     => 12345,
        'forma_pagamento'               => 'pix',
    ]);
}

function onda5fin_cleanup(int $cobrancaId): void
{
    Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobrancaId)
        ->forceDelete();
    Cobranca::withoutGlobalScopes()->where('id', $cobrancaId)->forceDelete();
}

it('CobrancaPaga business_id=1 cria Titulo a receber em fin_titulos', function () {
    $cobranca = onda5fin_makeCobranca(1, 9990);

    $event = new CobrancaPaga(
        cobrancaId: $cobranca->id,
        businessId: 1,
        valorPagoCentavos: 9990,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: 12345,
    );

    (new OnCobrancaPagaCreateFinanceiroTitulo())->handle($event);

    $titulo = Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobranca->id)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->tipo)->toBe('receber');
    expect((float) $titulo->valor_total)->toBe(99.90);
    expect($titulo->metadata['source'] ?? null)->toBe('paymentgateway_cobranca');

    onda5fin_cleanup($cobranca->id);
});

it('CobrancaPaga business_id!=1 é ignorada (escopo dogfooding)', function () {
    $cobranca = onda5fin_makeCobranca(99, 5000);

    $event = new CobrancaPaga(
        cobrancaId: $cobranca->id,
        businessId: 99,
        valorPagoCentavos: 5000,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'sale',
        origemId: null,
    );

    (new OnCobrancaPagaCreateFinanceiroTitulo())->handle($event);

    $count = Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobranca->id)
        ->count();

    expect($count)->toBe(0);

    onda5fin_cleanup($cobranca->id);
});

it('CobrancaPaga rodando 2x não duplica Titulo (idempotência)', function () {
    $cobranca = onda5fin_makeCobranca(1, 9990);

    $event = new CobrancaPaga(
        cobrancaId: $cobranca->id,
        businessId: 1,
        valorPagoCentavos: 9990,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: 12345,
    );

    $listener = new OnCobrancaPagaCreateFinanceiroTitulo();
    $listener->handle($event);
    $listener->handle($event);

    $count = Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobranca->id)
        ->count();

    expect($count)->toBe(1);

    onda5fin_cleanup($cobranca->id);
});
