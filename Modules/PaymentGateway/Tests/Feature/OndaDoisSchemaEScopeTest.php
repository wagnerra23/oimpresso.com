<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

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
    // Contrato ADR 0093: usuário AUTENTICADO vê só rows do próprio business.
    // ScopeByBusiness é fail-open sem auth (CLI/jobs — design documentado em
    // App\Concerns\HasBusinessScope + Jana/MultiTenantIsolationTest), então o
    // setup canônico é actingAs + session('user.business_id') (padrão Wave 7).
    $user = \App\User::where('business_id', 1)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=1 — semear DB (ADR 0101).');
    }

    $credBiz1 = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter biz=1',
        'config_json'  => ['token' => 'fake'],
    ]);
    $credBiz99 = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id'  => 99,
        'gateway_key'  => 'asaas',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Asaas biz=99',
        'config_json'  => ['token' => 'fake'],
    ]);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    // Autenticado em biz=1: vê a própria, NUNCA a de biz=99
    $ids = PaymentGatewayCredential::query()->pluck('id')->all();
    expect($ids)->toContain($credBiz1->id)
        ->and($ids)->not->toContain($credBiz99->id);

    // Sessão trocada pra biz=99: a credencial de biz=1 some da query
    session(['user.business_id' => 99]);
    expect(PaymentGatewayCredential::query()->pluck('id')->all())
        ->not->toContain($credBiz1->id);
});

it('global scope HasBusinessScope isola Cobranca entre tenants', function () {
    $user = \App\User::where('business_id', 1)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=1 — semear DB (ADR 0101).');
    }

    $chave = 'retriage:'.uniqid();
    $cobBiz1 = Cobranca::withoutGlobalScopes()->create([
        'business_id'     => 1,
        'tipo'            => 'boleto',
        'status'          => 'emitida',
        'valor_centavos'  => 10000,
        'vencimento'      => now()->addDays(5),
        'descricao'       => 'Teste biz=1',
        'idempotency_key' => $chave.':b1',
    ]);
    $cobBiz99 = Cobranca::withoutGlobalScopes()->create([
        'business_id'     => 99,
        'tipo'            => 'boleto',
        'status'          => 'emitida',
        'valor_centavos'  => 20000,
        'vencimento'      => now()->addDays(5),
        'descricao'       => 'Teste biz=99',
        'idempotency_key' => $chave.':b99',
    ]);

    $this->actingAs($user);
    session(['user.business_id' => 1]);

    $ids = Cobranca::query()->pluck('id')->all();
    expect($ids)->toContain($cobBiz1->id)
        ->and($ids)->not->toContain($cobBiz99->id);

    session(['user.business_id' => 99]);
    expect(Cobranca::query()->pluck('id')->all())
        ->not->toContain($cobBiz1->id);
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
