<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Models\Titulo;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Jobs\ProcessarWebhookPixInterJob;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-FIN-032 (Onda 26) — Integração end-to-end.
 *
 * Fluxo cross-module: PaymentGateway worker dispara CobrancaPaga →
 * Financeiro listener OnCobrancaPagaCreateFinanceiroTitulo cria Titulo
 * (e TituloBaixa quando conta bancária resolvida).
 *
 * Garante que o ciclo dogfooding Onda 5 + Onda 26 fecha sem rework:
 *   - Wagner emite PIX cobrança via wizard (Onda 5)
 *   - Cliente paga PIX
 *   - Inter envia webhook → controller valida HMAC → Job enfileira
 *   - Job marca Cobranca paga + dispara CobrancaPaga
 *   - Listener Financeiro cria Titulo origem='manual' + origem_id=cobranca.id
 *
 * Multi-tenant Tier 0 (ADR 0093/0101): biz=1 — listener Onda 5 só processa biz=1.
 */

beforeEach(function () {
    $this->credential = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Onda 26',
        'config_json'  => [
            'client_id'      => 'fake',
            'client_secret'  => 'fake',
            'webhook_secret' => 'integration-secret',
        ],
    ]);

    // FinanceiroServiceProvider::registerPaymentGatewayListeners() guarda o registro
    // atrás de uma flag ESTÁTICA (`private static bool $paymentgatewayListenersRegistered`).
    // Em produção isso é inócuo — um boot por request. Na suíte NÃO: a estática sobrevive
    // entre testes do mesmo processo PHP, enquanto o Laravel recria a aplicação (e o event
    // dispatcher) a cada teste. Resultado: do 2º teste em diante o provider retorna cedo e
    // CobrancaPaga fica SEM listener — o evento dispara, ninguém escuta, nenhum Titulo é
    // criado e nenhum log aparece (o guard que retorna não loga).
    //
    // Por isso este teste de INTEGRAÇÃO registra o listener explicitamente: sem isso ele
    // depende da posição na suíte, que é o que o fazia falhar de forma determinística.
    // ⚠️ Este registro NÃO prova que o provider registra em produção — prova só o trecho
    // job → service → event → listener → Titulo. A flag estática é um defeito à parte,
    // reportado pra decisão [W] (mexer nela toca criação de título = REGRA MESTRE valor).
    Event::listen(CobrancaPaga::class, [OnCobrancaPagaCreateFinanceiroTitulo::class, 'handle']);
});

it('worker → CobrancaPaga → listener Financeiro cria Titulo a receber quitado/aberto', function () {
    // 1. Setup: Cobranca emitida previamente (estado pré-pagamento)
    $cobranca = Cobranca::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'gateway_external_id'           => 'integration-pix-001',
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => 30000,
        'idempotency_key'               => 'integration-idem-001',
        'payer_cpf_cnpj'                => '12345678900',
        'payer_name'                    => 'João Integração',
        'descricao'                     => 'Mensalidade SaaS',
        'origem_type'                   => 'subscription_license',
        'origem_id'                     => 42,
        'payload_gateway'               => [],
    ]);

    // 2. Webhook log já criado (controller side)
    $log = InterWebhookLog::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'txid'                          => 'integration-pix-001',
        'valor_centavos'                => 30000,
        'data_pagamento'                => '2026-05-20 14:00:00',
        'signature_valid'               => true,
        'status'                        => 'received',
        'payload'                       => ['txid' => 'integration-pix-001'],
    ]);

    // 3. Worker roda síncrono (event dispatcher real — listener Financeiro reage)
    app()->call([new ProcessarWebhookPixInterJob($log->id, 1), 'handle']);

    // 4. Cobranca foi marcada paga
    $cobranca->refresh();
    expect($cobranca->status)->toBe('paga');

    // 5. Listener Financeiro criou Titulo (sem conta bancária → status='aberto')
    $titulo = Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobranca->id)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->numero)->toBe('PG-' . $cobranca->id);
    expect($titulo->tipo)->toBe('receber');
    expect((float) $titulo->valor_total)->toBe(300.0);
    expect($titulo->metadata['source'] ?? null)->toBe('paymentgateway_cobranca');

    // 6. Log marcado processed
    $log->refresh();
    expect($log->status)->toBe('processed');
    expect($log->cobranca_id)->toBe($cobranca->id);
});

it('UC-COB-06 · worker NÃO duplica Titulo em re-run (idempotência cross-module)', function () {
    $cobranca = Cobranca::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'gateway_external_id'           => 'integration-pix-002',
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => 10000,
        'idempotency_key'               => 'integration-idem-002',
        'payload_gateway'               => [],
    ]);

    $log = InterWebhookLog::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'txid'                          => 'integration-pix-002',
        'valor_centavos'                => 10000,
        'signature_valid'               => true,
        'status'                        => 'received',
        'payload'                       => [],
    ]);

    // 1ª execução
    app()->call([new ProcessarWebhookPixInterJob($log->id, 1), 'handle']);
    // 2ª execução (simula retry do queue worker mesma linha)
    app()->call([new ProcessarWebhookPixInterJob($log->id, 1), 'handle']);

    // Apenas 1 Titulo criado (listener checa origem_id ANTES de criar)
    $count = Titulo::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('origem', 'manual')
        ->where('origem_id', $cobranca->id)
        ->count();

    expect($count)->toBe(1);
});
