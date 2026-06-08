<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class);

/**
 * Onda 2 — ADR 0170.
 *
 * Smoke do schema novo + isolamento multi-tenant Tier 0 (ADR 0093) +
 * idempotência (UNIQUE constraints).
 *
 * Princípio ADR 0101: testes usam business_id = 1, NUNCA business_id de
 * cliente real (4 ROTA LIVRE).
 */

it('cria 3 tabelas novas com schema canônico', function () {
    expect(Schema::hasTable('payment_gateway_credentials'))->toBeTrue();
    expect(Schema::hasTable('cobrancas'))->toBeTrue();
    expect(Schema::hasTable('gateway_webhook_events'))->toBeTrue();
});

it('payment_gateway_credentials tem colunas canônicas', function () {
    foreach ([
        'id', 'business_id', 'gateway_key', 'ambiente', 'ativo',
        'nome_display', 'config_json', 'conta_bancaria_id',
        'health_status', 'health_checked_at', 'created_at', 'updated_at',
    ] as $col) {
        expect(Schema::hasColumn('payment_gateway_credentials', $col))->toBeTrue();
    }
});

it('cobrancas tem colunas canônicas', function () {
    foreach ([
        'id', 'business_id', 'payment_gateway_credential_id',
        'gateway_external_id', 'tipo', 'status',
        'valor_centavos', 'valor_pago_centavos', 'vencimento', 'paga_em',
        'contact_id', 'payer_cpf_cnpj', 'payer_name', 'payer_email',
        'descricao', 'idempotency_key', 'origem_type', 'origem_id',
        'linha_digitavel', 'codigo_barras', 'pix_emv', 'pix_qr_code_path',
        'boleto_pdf_url', 'nosso_numero', 'forma_pagamento',
        'payload_gateway', 'created_at', 'updated_at',
    ] as $col) {
        expect(Schema::hasColumn('cobrancas', $col))->toBeTrue();
    }
});

it('gateway_webhook_events tem colunas canônicas', function () {
    foreach ([
        'id', 'business_id', 'payment_gateway_credential_id',
        'gateway_key', 'evento', 'gateway_event_id', 'cobranca_id',
        'payload', 'signature_valid', 'processed_at', 'error_message',
        'created_at', 'updated_at',
    ] as $col) {
        expect(Schema::hasColumn('gateway_webhook_events', $col))->toBeTrue();
    }
});

it('global scope HasBusinessScope isola PaymentGatewayCredential entre tenants', function () {
    // Cria credencial em biz=1
    auth()->logout();
    session(['business.id' => 1]);

    PaymentGatewayCredential::create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter biz=1',
        'config_json'  => ['token' => 'fake'],
    ]);

    // Em biz=1 vê 1
    expect(PaymentGatewayCredential::count())->toBe(1);

    // Trocar sessão pra biz=99
    session(['business.id' => 99]);

    // Em biz=99 não vê
    expect(PaymentGatewayCredential::count())->toBe(0);
});

it('global scope HasBusinessScope isola Cobranca entre tenants', function () {
    session(['business.id' => 1]);

    Cobranca::create([
        'business_id'     => 1,
        'tipo'            => 'boleto',
        'status'          => 'emitida',
        'valor_centavos'  => 10000,
        'vencimento'      => now()->addDays(5),
        'descricao'       => 'Teste biz=1',
        'idempotency_key' => 'biz1:test:1',
    ]);

    expect(Cobranca::count())->toBe(1);

    session(['business.id' => 99]);
    expect(Cobranca::count())->toBe(0);
});

it('UNIQUE(business_id, idempotency_key) impede emissão dupla mesmo business', function () {
    session(['business.id' => 1]);

    Cobranca::create([
        'business_id'     => 1,
        'tipo'            => 'pix_cob',
        'status'          => 'pending',
        'valor_centavos'  => 5000,
        'vencimento'      => now()->addDay(),
        'descricao'       => 'Original',
        'idempotency_key' => 'sale:777',
    ]);

    expect(fn () => Cobranca::create([
        'business_id'     => 1,
        'tipo'            => 'pix_cob',
        'status'          => 'pending',
        'valor_centavos'  => 5000,
        'vencimento'      => now()->addDay(),
        'descricao'       => 'Duplicada',
        'idempotency_key' => 'sale:777',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('mesma idempotency_key em businesses diferentes NÃO conflita', function () {
    // biz=1
    session(['business.id' => 1]);
    Cobranca::create([
        'business_id'     => 1,
        'tipo'            => 'boleto',
        'status'          => 'emitida',
        'valor_centavos'  => 10000,
        'vencimento'      => now()->addDay(),
        'descricao'       => 'biz=1',
        'idempotency_key' => 'invoice:1',
    ]);

    // biz=99 — mesma key, sem conflito
    session(['business.id' => 99]);
    expect(fn () => Cobranca::create([
        'business_id'     => 99,
        'tipo'            => 'boleto',
        'status'          => 'emitida',
        'valor_centavos'  => 20000,
        'vencimento'      => now()->addDay(),
        'descricao'       => 'biz=99',
        'idempotency_key' => 'invoice:1',
    ]))->not->toThrow(\Throwable::class);
});

it('UNIQUE(business_id, gateway_key, gateway_event_id) impede webhook dupla mesmo business', function () {
    session(['business.id' => 1]);

    GatewayWebhookEvent::create([
        'business_id'      => 1,
        'gateway_key'      => 'inter',
        'evento'           => 'cob.paid',
        'gateway_event_id' => 'evt_inter_001',
        'payload'          => ['raw' => 'ok'],
        'signature_valid'  => true,
    ]);

    expect(fn () => GatewayWebhookEvent::create([
        'business_id'      => 1,
        'gateway_key'      => 'inter',
        'evento'           => 'cob.paid',
        'gateway_event_id' => 'evt_inter_001',
        'payload'          => ['raw' => 'duplicado'],
        'signature_valid'  => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('gateway_event_id pode repetir CROSS-gateway (diferentes provedores)', function () {
    session(['business.id' => 1]);

    GatewayWebhookEvent::create([
        'business_id'      => 1,
        'gateway_key'      => 'inter',
        'evento'           => 'cob.paid',
        'gateway_event_id' => 'evt_collision',
        'payload'          => [],
        'signature_valid'  => true,
    ]);

    expect(fn () => GatewayWebhookEvent::create([
        'business_id'      => 1,
        'gateway_key'      => 'asaas',
        'evento'           => 'PAYMENT_RECEIVED',
        'gateway_event_id' => 'evt_collision',
        'payload'          => [],
        'signature_valid'  => true,
    ]))->not->toThrow(\Throwable::class);
});

it('Models castam JSON e datas corretamente', function () {
    session(['business.id' => 1]);

    $cred = PaymentGatewayCredential::create([
        'business_id'       => 1,
        'gateway_key'       => 'asaas',
        'ambiente'          => 'sandbox',
        'ativo'             => true,
        'config_json'       => ['api_key' => 'x'],
        'health_checked_at' => now(),
    ]);

    expect($cred->config_json)->toBeArray();
    expect($cred->ativo)->toBeTrue();
    expect($cred->health_checked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
