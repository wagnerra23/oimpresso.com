<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Jobs\ProcessarWebhookPixInterJob;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-FIN-032 (Onda 26) — Cobertura webhook PIX Inter dedicado.
 *
 * Cenários:
 *   1. Signature válida + payload completo → InterWebhookLog created + job enfileirado
 *   2. Signature inválida → 401 + nenhuma linha em inter_webhook_log
 *   3. credentialId inexistente → 404
 *   4. Sem secret cadastrado → 401 (fail-secure)
 *   5. Txid duplicado mesmo credencial → idempotente (1 linha, segundo POST devolve duplicated)
 *   6. Mesmo txid em credencial DIFERENTE → 2 linhas (UNIQUE inclui credential_id)
 *   7. Worker processado: cobranca existe → status=processed + CobrancaPaga dispatch
 *   8. Worker: cobranca NÃO existe → status=titulo_nao_encontrado + log warning
 *   9. Worker job-rerun mesma linha já processed → cedo-return (idempotência)
 *  10. business_id resolvido corretamente pela credentialId (Tier 0 multi-tenant)
 *
 * ADR 0093 multi-tenant: biz=1 (NUNCA biz=4 cliente — ADR 0101).
 * ADR 0143 FSM: Titulo update direto (sem trait GuardsFsmTransitions yet).
 * ADR 0170 dogfooding ciclo PaymentGateway.
 */

beforeEach(function () {
    $this->credential = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Test',
        'config_json'  => [
            'client_id'      => 'fake-client',
            'client_secret'  => 'fake-secret',
            'webhook_secret' => 'super-secret-hmac-key',
        ],
    ]);
});

/**
 * Helper — gera POST com HMAC válido.
 */
function postWithValidSignature($test, int $credId, array $payload, string $secret = 'super-secret-hmac-key')
{
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $raw, $secret);

    return $test->call(
        method: 'POST',
        uri: "/webhooks/inter/{$credId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X_INTER_SIGNATURE' => $signature,
            'HTTP_ACCEPT'           => 'application/json',
        ],
        content: $raw,
    );
}

it('aceita webhook PIX com signature HMAC válida e cria InterWebhookLog', function () {
    Queue::fake();

    $payload = [
        'pix' => [[
            'endToEndId' => 'E12345678202605200001',
            'txid'       => 'inter-pix-abc-001',
            'valor'      => '150.50',
            'horario'    => '2026-05-20T12:34:56Z',
            'pagador'    => [
                'cpf'  => '12345678900',
                'nome' => 'João da Silva',
            ],
        ]],
    ];

    $response = postWithValidSignature($this, $this->credential->id, $payload);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'processed' => 1, 'duplicated' => 0]);

    $log = InterWebhookLog::withoutGlobalScopes()->first();
    expect($log)->not->toBeNull();
    expect($log->business_id)->toBe(1);
    expect($log->payment_gateway_credential_id)->toBe($this->credential->id);
    expect($log->txid)->toBe('inter-pix-abc-001');
    expect($log->endToEndId)->toBe('E12345678202605200001');
    expect($log->valor_centavos)->toBe(15050);
    expect($log->signature_valid)->toBeTrue();
    expect($log->status)->toBe('received');
    // LGPD: CPF NÃO grava em raw — apenas redacted
    expect($log->payer_cpf_cnpj_redacted)->not->toContain('12345678900');

    Queue::assertPushed(ProcessarWebhookPixInterJob::class, function ($job) use ($log) {
        return $job->interWebhookLogId === $log->id && $job->businessId === 1;
    });
});

it('rejeita webhook com signature HMAC inválida → 401', function () {
    Queue::fake();

    $payload = ['pix' => [['txid' => 'abc', 'valor' => '10.00']]];
    $raw = json_encode($payload);

    $response = $this->call(
        method: 'POST',
        uri: "/webhooks/inter/{$this->credential->id}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_X_INTER_SIGNATURE' => 'assinatura-falsa-deadbeef',
            'HTTP_ACCEPT'           => 'application/json',
        ],
        content: $raw,
    );

    $response->assertStatus(401);
    $response->assertJson(['ok' => false, 'error' => 'signature_invalid']);

    expect(InterWebhookLog::withoutGlobalScopes()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejeita webhook quando credentialId não existe → 404', function () {
    Queue::fake();
    $payload = ['pix' => [['txid' => 'x', 'valor' => '1.00']]];

    $response = postWithValidSignature($this, 99999, $payload);

    $response->assertStatus(404);
    $response->assertJson(['ok' => false, 'error' => 'credential_not_found']);
    Queue::assertNothingPushed();
});

it('fail-secure: credencial SEM webhook_secret cadastrado → 401', function () {
    Queue::fake();

    $credSemSecret = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter sem secret',
        'config_json'  => [
            'client_id'     => 'x',
            'client_secret' => 'y',
            // sem webhook_secret
        ],
    ]);

    $payload = ['pix' => [['txid' => 't', 'valor' => '1.00']]];
    $response = postWithValidSignature($this, $credSemSecret->id, $payload);

    $response->assertStatus(401);
    Queue::assertNothingPushed();
});

it('txid duplicado mesma credencial → idempotente (devolve duplicated=1)', function () {
    Queue::fake();

    $payload = ['pix' => [['txid' => 'inter-dup-001', 'valor' => '50.00']]];

    postWithValidSignature($this, $this->credential->id, $payload)->assertOk();
    $r2 = postWithValidSignature($this, $this->credential->id, $payload);

    $r2->assertOk();
    $r2->assertJson(['processed' => 0, 'duplicated' => 1]);

    expect(InterWebhookLog::withoutGlobalScopes()->count())->toBe(1);
});

it('mesmo txid em credencial DIFERENTE NÃO colide', function () {
    Queue::fake();

    $cred2 = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter 2',
        'config_json'  => [
            'client_id'      => 'a',
            'client_secret'  => 'b',
            'webhook_secret' => 'outro-secret',
        ],
    ]);

    $payload = ['pix' => [['txid' => 'shared-txid', 'valor' => '10.00']]];

    postWithValidSignature($this, $this->credential->id, $payload)->assertOk();
    postWithValidSignature($this, $cred2->id, $payload, 'outro-secret')->assertOk();

    expect(InterWebhookLog::withoutGlobalScopes()->count())->toBe(2);
});

it('payload sem chave `pix` retorna 200 vazio (Inter envia teste assim)', function () {
    Queue::fake();

    $response = postWithValidSignature($this, $this->credential->id, []);
    $response->assertOk();
    $response->assertJson(['processed' => 0, 'duplicated' => 0]);

    expect(InterWebhookLog::withoutGlobalScopes()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('Job worker processa Cobranca existente: marca paga + dispatch CobrancaPaga + status=processed', function () {
    Event::fake([CobrancaPaga::class]);

    // Setup: Cobranca emitida previamente
    $cobranca = Cobranca::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'gateway_external_id'           => 'inter-pix-job-001',
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => 25000,
        'idempotency_key'               => 'idem-key-001',
        'payload_gateway'               => [],
    ]);

    // Log já criado (simulando controller)
    $log = InterWebhookLog::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'txid'                          => 'inter-pix-job-001',
        'valor_centavos'                => 25000,
        'data_pagamento'                => '2026-05-20 12:00:00',
        'signature_valid'               => true,
        'status'                        => 'received',
        'payload'                       => ['txid' => 'inter-pix-job-001'],
    ]);

    (new ProcessarWebhookPixInterJob($log->id, 1))->handle();

    $log->refresh();
    $cobranca->refresh();

    expect($log->status)->toBe('processed');
    expect($log->cobranca_id)->toBe($cobranca->id);
    expect($log->processed_at)->not->toBeNull();
    expect($cobranca->status)->toBe('paga');
    expect($cobranca->forma_pagamento)->toBe('pix');
    expect($cobranca->valor_pago_centavos)->toBe(25000);

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) use ($cobranca) {
        return $e->cobrancaId === $cobranca->id
            && $e->businessId === 1
            && $e->formaPagamento === 'pix';
    });
});

it('Job worker: Cobranca NÃO encontrada → status=titulo_nao_encontrado, sem CobrancaPaga', function () {
    Event::fake([CobrancaPaga::class]);

    $log = InterWebhookLog::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'txid'                          => 'inexistente-txid-xyz',
        'valor_centavos'                => 1000,
        'signature_valid'               => true,
        'status'                        => 'received',
        'payload'                       => ['txid' => 'inexistente-txid-xyz'],
    ]);

    (new ProcessarWebhookPixInterJob($log->id, 1))->handle();

    $log->refresh();
    expect($log->status)->toBe('titulo_nao_encontrado');
    expect($log->processed_at)->not->toBeNull();
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('Job worker idempotente: re-rodar linha já processed → cedo-return sem re-disparar', function () {
    Event::fake([CobrancaPaga::class]);

    $log = InterWebhookLog::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->credential->id,
        'txid'                          => 'reprocessado-001',
        'valor_centavos'                => 5000,
        'signature_valid'               => true,
        'status'                        => 'processed', // já processado
        'processed_at'                  => now(),
        'payload'                       => ['txid' => 'reprocessado-001'],
    ]);

    (new ProcessarWebhookPixInterJob($log->id, 1))->handle();

    Event::assertNotDispatched(CobrancaPaga::class);
});

it('business_id resolvido pela credentialId (Tier 0 multi-tenant)', function () {
    Queue::fake();

    // Credencial biz=99 (outro tenant)
    $credBiz99 = PaymentGatewayCredential::query()->create([
        'business_id'  => 99,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter biz=99',
        'config_json'  => [
            'client_id'      => 'a',
            'client_secret'  => 'b',
            'webhook_secret' => 'secret-biz-99',
        ],
    ]);

    $payload = ['pix' => [['txid' => 'biz99-tx', 'valor' => '99.00']]];
    postWithValidSignature($this, $credBiz99->id, $payload, 'secret-biz-99')->assertOk();

    $log = InterWebhookLog::withoutGlobalScopes()
        ->where('payment_gateway_credential_id', $credBiz99->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->business_id)->toBe(99);

    Queue::assertPushed(ProcessarWebhookPixInterJob::class, function ($job) {
        return $job->businessId === 99;
    });
});
